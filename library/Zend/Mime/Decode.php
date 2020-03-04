<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Mime
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id$
 */

/**
 * @see Zend_Mime
 */
require_once 'Zend/Mime.php';

/**
 * @category   Zend
 * @package    Zend_Mime
 * @copyright  Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Zend_Mime_Decode
{
    /**
     * Explode MIME multipart string into seperate parts
     *
     * Parts consist of the header and the body of each MIME part.
     *
     * @param  string $body     raw body of message
     * @param  string $boundary boundary as found in content-type
     * @return array parts with content of each part, empty if no parts found
     * @throws Zend_Exception
     */
    public static function splitMime($body, $boundary)
    {
        // TODO: we're ignoring \r for now - is this function fast enough and is it safe to asume noone needs \r?
        $body = str_replace("\r", '', $body);

        $start = 0;
        $res = array();
        // find every mime part limiter and cut out the
        // string before it.
        // the part before the first boundary string is discarded:
        $p = strpos($body, '--' . $boundary . "\n", $start);
        if ($p === false) {
            // no parts found!
            return array();
        }

        // position after first boundary line
        $start = $p + 3 + strlen($boundary);

        while (($p = strpos($body, '--' . $boundary . "\n", $start)) !== false) {
            $res[] = substr($body, $start, $p-$start);
            $start = $p + 3 + strlen($boundary);
        }

        // no more parts, find end boundary
        $p = strpos($body, '--' . $boundary . '--', $start);
        if ($p===false) {
            /**
             * Fix by Uniques - if the part doesn't have end boundary then return all text from start boundary to the
             * end of body instead of throwing exception
             */
            // throw new Zend_Exception('Not a valid Mime Message: End Missing');
            $res[] = substr($body, $start);
            return $res;
        }

        // the remaining part also needs to be parsed:
        $res[] = substr($body, $start, $p-$start);

        return $res;
    }

    /**
     * decodes a mime encoded String and returns a
     * struct of parts with header and body
     *
     * @param  string $message  raw message content
     * @param  string $boundary boundary as found in content-type
     * @param  string $EOL EOL string; defaults to {@link Zend_Mime::LINEEND}
     * @return array|null parts as array('header' => array(name => value), 'body' => content), null if no parts found
     * @throws Zend_Exception
     */
    public static function splitMessageStruct(
        $message, $boundary, $EOL = Zend_Mime::LINEEND
    )
    {
        $parts = self::splitMime($message, $boundary);
        if (count($parts) <= 0) {
            return null;
        }
        $result = array();
        foreach ($parts as $part) {
            if (strlen($part) < 26675200) { // size of part < 25MB
                self::splitMessage($part, $headers, $body, $EOL);
                $result[] = array(
                    'header' => $headers,
                    'body'   => $body
                );
            }
        }

        return count($result) == 0 ? null : $result;
    }

    /**
     * Added by Uniques
     * @param $message
     * @return array|mixed
     */
    private static function extractHeadersWithMailparse($message)
    {
        $result = array();
        $mail   = mailparse_msg_create();
        mailparse_msg_parse($mail, $message);
        $structure = mailparse_msg_get_structure($mail);
        foreach ($structure as $s) {
            $part     = mailparse_msg_get_part($mail, $s);
            $partData = mailparse_msg_get_part_data($part);
            if ($s == 1) {
                $result = $partData['headers'];
            }
        }

        // simulate ZF1 behaviour; extract from multiline headers last line
        foreach ($result as $key => $val) {
            if (is_array($val)) {
                $result[$key] = array_pop($val);
            }
        }

        return $result;
    }

    /**
     * split a message in header and body part, if no header or an
     * invalid header is found $headers is empty
     *
     * The charset of the returned headers depend on your iconv settings.
     *
     * @param  string $message raw message with header and optional content
     * @param  array  $headers output param, array with headers as array(name => value)
     * @param  string $body    output param, content of message
     * @param  string $EOL EOL string; defaults to {@link Zend_Mime::LINEEND}
     * @return null
     */
    public static function splitMessage(
        $message, &$headers, &$body, $EOL = Zend_Mime::LINEEND
    )
    {
        if (strlen($message) >= 26675200) { // size of part < 25MB
            return;
        }
        // check for valid header at first line
        $firstline = strtok($message, "\n");
        if (!preg_match('%^[^\s]+[^:]*:%', $firstline)) {
            $headers = array();
            // TODO: we're ignoring \r for now - is this function fast enough and is it safe to asume noone needs \r?
            $body = str_replace(
                array(
                    "\r",
                    "\n"
                ), array(
                    '',
                    $EOL
                ), $message
            );

            return;
        }
        // see @ZF2-372, pops the first line off a message if it doesn't contain a header
        if (true) {
            $parts = explode(':', $firstline, 2);
            if (count($parts) != 2) {
                $message = substr($message, strpos($message, $EOL)+1);
            }
        }

        // find an empty line between headers and body
        // default is set new line
        if (strpos($message, $EOL . $EOL)) {
            list($headers, $body) = explode($EOL . $EOL, $message, 2);
            // next is the standard new line
        } else {
            if ($EOL != "\r\n" && strpos($message, "\r\n\r\n")) {
                list($headers, $body) = explode("\r\n\r\n", $message, 2);
                // next is the other "standard" new line
            } else {
                if ($EOL != "\n" && strpos($message, "\n\n")) {
                    list($headers, $body) = explode("\n\n", $message, 2);
                } else if (strpos($message, "\r\n")) {
                    list($headers, $body) = explode("\r\n", $message, 2);
                    // at last resort find anything that looks like a new line
                } else {
                    @list($headers, $body) =
                        @preg_split("%([\r\n]+)\\1%U", $message, 2);
                }
            }
        }

        // Commented by Uniques team
        // $headers = iconv_mime_decode_headers(
        //     $headers, ICONV_MIME_DECODE_CONTINUE_ON_ERROR
        // );
        $headers = self::fromString($headers, $EOL);

        if(empty($headers)) {
            $headers = self::extractHeadersWithMailparse($message);
        }

        // normalize header names
        foreach ($headers as $name => $header) {
            $lower = strtolower($name);
            if ($lower == $name) {
                continue;
            }
            unset($headers[$name]);
            if (!isset($headers[$lower])) {
                $headers[$lower] = $header;
                continue;
            }
            if (is_array($headers[$lower])) {
                $headers[$lower][] = $header;
                continue;
            }
            $headers[$lower] = array(
                $headers[$lower],
                $header
            );
        }
    }

    /**
     * Method added by Uniques from Zend Framework 2
     * @param $string
     * @param string $EOL
     * @return array
     * @throws Exception
     */
    public static function fromString($string, $EOL = Zend_Mime::LINEEND)
    {
        $headers     = array();
        $currentLine = '';

        // iterate the header lines, some might be continuations
        foreach (explode($EOL, $string) as $line) {
            // check if a header name is present
            if (preg_match('/^(?P<name>[^()><@,;:\"\\/\[\]?=}{ \t]+):.*$/', $line, $matches)) {
                if ($currentLine) {
                    // a header name was present, then store the current complete line
                    $header = self::addHeaderLine($currentLine);
                    $headers [$header['name']]= $header['value'];
                }
                $currentLine = trim($line);
            } elseif (preg_match('/^\s+.*$/', $line, $matches)) {
                // continuation: append to current line
                $currentLine .= trim($line);
            } elseif (preg_match('/^\s*$/', $line)) {
                // empty line indicates end of headers
                break;
            } else {
                return $headers;
                /*// Line does not match header format!
                throw new Exception(sprintf(
                    'Line "%s" does not match header format!',
                    $line
                ));*/
            }
        }
        if ($currentLine) {
            $header = self::addHeaderLine($currentLine);
            $headers [$header['name']]= $header['value'];
        }
        return $headers;
    }

    /**
     * Method added by Uniques from Zend Framework 2
     * @param $headerFieldNameOrLine
     * @return mixed
     * @throws Exception
     */
    public static function addHeaderLine($headerFieldNameOrLine)
    {
        if (!is_string($headerFieldNameOrLine)) {
            throw new Exception('addHeaderLine error');
        }

        $header = self::genericHeaderFromString($headerFieldNameOrLine);
        return self::normalizeFieldName($header);
    }

    /**
     * Method added by Uniques from Zend Framework 2
     * @param $fieldName
     * @return mixed
     */
    protected static function normalizeFieldName($fieldName)
    {
        $fieldName['name'] = str_replace(array('_', ' ', '.'), '', strtolower($fieldName['name']));
        return $fieldName;
    }

    /**
     * Method added by Uniques from Zend Framework 2
     * @param $headerLine
     * @return array
     * @throws Exception
     */
    public static function genericHeaderFromString($headerLine)
    {
        $decodedLine = iconv_mime_decode($headerLine, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
        $parts = explode(':', $decodedLine, 2);
        if (count($parts) != 2) {
            throw new Exception('Header must match with the format "name: value"');
        }

        $fieldValue = ltrim($parts[1]);
        if (empty($fieldValue) || preg_match('/^\s+$/', $fieldValue)) {
            $fieldValue = '';
        }

        $header =array('name'=>$parts[0], 'value'=>$fieldValue);

        return $header;
    }

    /**
     * split a content type in its different parts
     *
     * @param  string $type       content-type
     * @param  string $wantedPart the wanted part, else an array with all parts is returned
     * @return string|array wanted part or all parts as array('type' => content-type, partname => value)
     */
    public static function splitContentType($type, $wantedPart = null)
    {
        return self::splitHeaderField($type, $wantedPart, 'type');
    }

    /**
     * split a header field like content type in its different parts
     *
     * @param  string     $field
     * @param  string $wantedPart the wanted part, else an array with all parts is returned
     * @param  int|string $firstName  key name for the first part
     * @throws Zend_Exception
     * @return string|array wanted part or all parts as array($firstName => firstPart, partname => value)
     */
    public static function splitHeaderField(
        $field, $wantedPart = null, $firstName = 0
    )
    {
        $wantedPart = strtolower($wantedPart);
        $firstName = strtolower($firstName);

        // special case - a bit optimized
        if ($firstName === $wantedPart) {
            $field = strtok($field, ';');

            return $field[0] == '"' ? substr($field, 1, -1) : $field;
        }

        $field = $firstName . '=' . $field;
        if (!preg_match_all('%([^=\s]+)\s*=\s*("[^"]+"|[^;]+)(;\s*|$)%', $field, $matches)) {
            throw new Zend_Exception('not a valid header field');
        }

        if ($wantedPart) {
            foreach ($matches[1] as $key => $name) {
                if (strcasecmp($name, $wantedPart)) {
                    continue;
                }
                if ($matches[2][$key][0] != '"') {
                    return $matches[2][$key];
                }

                return substr($matches[2][$key], 1, -1);
            }

            return null;
        }

        $split = array();
        foreach ($matches[1] as $key => $name) {
            $name = strtolower($name);
            if ($matches[2][$key][0] == '"') {
                $split[$name] = substr($matches[2][$key], 1, -1);
            } else {
                $split[$name] = $matches[2][$key];
            }
        }

        return $split;
    }

    /**
     * decode a quoted printable encoded string
     *
     * The charset of the returned string depends on your iconv settings.
     *
     * @param  string encoded string
     * @return string decoded string
     */
    public static function decodeQuotedPrintable($string)
    {
        return iconv_mime_decode($string, ICONV_MIME_DECODE_CONTINUE_ON_ERROR);
    }
}
