# uniquesca/zf1

A downstream fork of [Shardj/zf1-future](https://github.com/Shardj/zf1-future),
which is itself the maintained continuation of the archived
[zendframework/zf1](https://github.com/zendframework/zf1).

We track upstream and carry a small set of local customizations on top. The
Composer package name stays `uniquesca/zf1` so existing consumers do not have
to change their `require` entries.

## PHP support

Inherited from upstream: **PHP 7.4 and above**, including 8.0 through 8.3.
The frozen `1.12.20.x` line is the one to use below 7.4.

## Versioning

Our own semver, independent of upstream's numbering:

- **Major** — we break something for consumers, or upstream does.
- **Minor** — a new upstream release merged in, no break.
- **Patch** — our own fixes.

`^2.0` is the constraint consumers want.

**The tag is the version.** `composer.json` deliberately has no `version` field, so
there is nothing to keep in step with the tag you cut. This is not cosmetic: when
a `version` field disagrees with the tag name, Composer **silently discards the
tag** — `Skipped tag X, tag (X) does not match version (Y) in composer.json` — and
it is discarded by Satis too, so the release simply never appears and `composer
require` reports the version as nonexistent. 2.0.1 through 2.0.4 were all lost
that way before the field was removed.

The upstream release we are merged up to is recorded in `composer.json` under
`extra.upstream.merged-to`, so "how far behind are we?" stays a one-line answer
without overloading the version number:

    "extra": {
        "upstream": {
            "repository": "https://github.com/Shardj/zf1-future",
            "merged-to": "1.25.1"
        }
    }

`Zend_Version::VERSION` deliberately does **not** track our package version. It
reports the Zend Framework version this is built from (`1.25.1`), because that
is what the constant means and what it is used for — it ends up in outbound
`User-Agent` headers and in RSS/Atom `<generator>` tags. Setting it to `2.x`
would claim to be Zend Framework 2, which was a different product entirely.

The pre-2.0 `1.12.20.x` line is frozen. It remains installable for consumers
still on PHP 5.6 / 7.x but will receive no further work. A `~1.12.20`
constraint cannot resolve to 2.x, so those consumers will not be dragged onto
a PHP 7.4+ release by a `composer update`.

## Local customizations

Everything below is a deliberate divergence from upstream. Keep this list
current — it is what makes the next upstream merge tractable.

| Area | File | What and why |
|---|---|---|
| Cache | `library/Zend/Cache/Backend/Memcached.php` | `load()` honours `$doNotTestCacheValidity` and returns the raw entry instead of unwrapping `$tmp[0]`. |
| i18n | `resources/languages/fr/Zend_Captcha.php` | French CAPTCHA messages. Upstream ships `fr/Zend_Validate.php` but no `fr` CAPTCHA translation. |
| TLD list | `library/Zend/Validate/Hostname.php` | Two appended blocks in `$_validTlds`: U-label forms for IDN TLDs upstream lists only in Punycode, and delegated TLDs upstream omits entirely. See below. |
| Session | `library/Zend/Session.php` | Missing `require_once` for the save-handler adapter, without which `setSaveHandler()` is fatal. **Upstream's own fix, carried early — not ours.** Drop when a merge brings in PR #543. See below. |

Plus `composer.json` (package identity) and this file.

### The TLD additions

`Zend_Validate_Hostname` compares the last label of the input **verbatim**
against `$_validTlds` (`$this->_tld = $matches[1]`, then `in_array()` on the raw
and lowercased forms). There is no Punycode conversion, so an `xn--` entry does
**not** match native-script input and vice versa — a TLD needs both forms listed
to be accepted both ways.

Upstream lists most IDN TLDs only in Punycode, so `example.бг` and
`example.商店` fail there while `example.xn--90ae` passes.

Upstream's array is left **completely untouched**. Everything we add sits in one
block appended after its last entry, so upstream edits merge cleanly. The block
has two parts:

1. **Backwards compatibility — 152 entries.** Every TLD the pre-2.0 fork
   accepted that upstream does not carry, restored *verbatim*. This includes
   TLDs IANA no longer delegates (`cartier`, `chrysler`, `mcdonalds`, `zippo`,
   the withdrawn ISO codes `an`/`bl`/`bq`/`eh`/`mf`/`tp`/`um`, and the IDN test
   TLDs) and 37 entries carrying U+200E/U+200F bidi marks exactly as the old
   list stored them.

   These are kept **on purpose**. Existing stored addresses must keep
   validating, so anything accepted before 2.0.0 is still accepted. Do not
   "clean up" this list against IANA — that is a breaking change.

2. **Completeness — 26 entries.** Delegated TLDs still absent after part 1, in
   whichever form was missing (A-label, U-label, or both).

Verified against IANA `tlds-alpha-by-domain` version 2026081500 and against the
pre-2.0 fork:

- all 1438 delegated TLDs validate in Punycode form
- all 151 with a native-script form validate that way too
- all 1682 distinct TLDs the old fork accepted still validate — zero regressions
- every upstream entry retained; no duplicates introduced

`tests/` has no coverage for this. When merging a new upstream release, re-run
those four checks rather than eyeballing the diff.

### The session save-handler `require_once`

`Zend_Session::setSaveHandler()` instantiates
`Zend_Session_SaveHandler_SessionHandlerInterfaceAdapter`, but upstream declares
that class in a file called `SaveHandlerInterfaceAdapter.php`. The two have never
agreed: the file was `SaveHandlerInterfaceAdaptor.php` until `d7b63473`
(2026-05-19) corrected "Adaptor" to "Adapter" without touching the class name or
the reference. PSR-0 derives the path from the class name, so it cannot find the
file, and **any consumer that calls `setSaveHandler()` takes a fatal
`Class not found`** — on every request, since that call belongs in bootstrap.
Applications with a DB-backed session handler hit this immediately; ones on the
default file handler never do, which is why it has survived this long upstream.

Upstream's fix is open as
[PR #543](https://github.com/Shardj/zf1-future/pull/543) and is **not** in
`1.25.1`. Its commit `f67b2e4e` is cherry-picked here, adding the missing
`require_once` beside the existing ones at the top of `Zend/Session.php`. That
resolves without an autoloader because the package declares
`include-path: ["library/"]`, so Composer prepends `library/`.

Unlike everything else in the table, this is not a Uniques customization — it is
upstream's own patch carried early, so it should have the shortest life of any
row here. When a merge brings PR #543 in, drop it; keeping both leaves a
duplicate `require_once`.

That is the whole delta. If it ever reaches zero, this fork can be retired in
favour of requiring `shardj/zf1-future` directly.

### Previously carried, now dropped

For anyone going back through history: this fork used to patch `Zend_Mail`,
`Zend_Mail_Part`, `Zend_Mail_Transport_Abstract` and `Zend_Mime_Decode` — an
optional `$send` flag and array return on `send()`, relaxed MIME header
validation, a 25 MB part cap, a ZF2 `fromString()` backport, and an
`ext-mailparse` header-extraction fallback. Those were written for an
application that has since migrated to Laminas, and were dropped in 2.0.0 in
favour of upstream's versions.

## Merging a new upstream release

    git remote add shardj https://github.com/Shardj/zf1-future.git
    git fetch shardj
    git merge shardj/master

The two repositories share history back to the `zendframework/zf1` EOL commit
(`136735e77`), so this is an ordinary merge — no cherry-picking or patch
replay.

Conflicts should be confined to `composer.json` (keep our `name`,
`description`, `homepage`, `branch-alias`, `extra.upstream` and `replace`, and
keep the `version` field *absent* if upstream reintroduces one) and possibly the
customized files above.

Afterwards:

1. Set `Zend_Version::VERSION` to upstream's new version — take theirs.
2. Update `extra.upstream.merged-to`.
3. Drop any backported upstream fix the merge has now brought in — currently the
   session `require_once` above, once PR #543 lands.
4. Update this file if the customization set changed.
5. Tag, by our own semver rules above. The tag is the only place the release
   version is stated.
