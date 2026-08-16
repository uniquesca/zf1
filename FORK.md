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

Plus `composer.json` (package identity) and this file.

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
`description`, `homepage`, `version`, `branch-alias`, `extra.upstream` and
`replace`) and possibly the two customized files above.

Afterwards:

1. Set `Zend_Version::VERSION` to upstream's new version — take theirs.
2. Bump `version` in `composer.json` by our own semver rules.
3. Update `extra.upstream.merged-to`.
4. Update this file if the customization set changed.
5. Tag.
