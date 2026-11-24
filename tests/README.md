# Local regression tests and admin-request diagnostics

## Run without WordPress or a live site

```sh
node --test tests/*.test.mjs
node --check opt/assets/js/admin-errors.js
node --check opt/assets/js/main.min.js
php -l opt/classes/setup.class.php
php -l opt/options/theme-options.php
php -l inc/nikki-sync.php
php -l tests/nikki-sync-diagnostics.test.php
php tests/nikki-sync-diagnostics.test.php
php -d zend.assertions=1 -d assert.exception=1 inc/word-stat.test.php
git diff --check
```

The JavaScript tests execute the actual CSF save function extracted from its
bundle and the actual Nikki inline handler extracted from PHP, using in-memory
DOM/request doubles. They do not send requests. Fixtures contain only dummy
identifiers. Coverage includes structured WordPress/jqXHR errors, function-valued
`error` properties, raw/HTML/invalid JSON responses, HTTP failures (including a
misleading `success` payload), nonce/action sentinel responses, rejected fetches,
body-read failures, processing exceptions, success-shape checks, button recovery,
retry, unsaved-state preservation, and the existing success path.

The PHP diagnostic tests run standalone, stub every HTTP request, and throw on
private option access, option writes, or transient writes. They must not be
loaded into WordPress or used as an HTTP endpoint. The diagnostic fixtures
exercise only failure paths; no real sync/save or authenticated upstream request
is performed.

If native PHP is unavailable, a disposable WordPress `@php-wasm/cli` installation
outside the repository can run the same PHP commands. Check actual output as
well as the wrapper exit status: the tested CLI wrapper returned zero for a PHP
fatal error. Do not claim PHP checks passed based only on that wrapper status.

## Editable-source/build boundary

In this checkout, `opt/assets/js/main.min.js` is the only CSF main JavaScript
implementation. There is no `opt/assets/js/main.js`, CSF source map, or CSF
package/build configuration. Unrelated vendored Kirki build files and public
`js/*.map` files do not rebuild the CSF bundle.

The CSF change is therefore a single bounded failure-callback replacement in
that distributed asset, with no wholesale re-minification. The readable,
authoritative diagnostic implementation is `opt/assets/js/admin-errors.js`;
edit it directly, with no build step. `opt/classes/setup.class.php` enqueues it
before CSF, versions it by modification time, and changes the CSF script version
to prevent reuse of the old failure callback. Nikki's inline listener remains
owned by `opt/options/theme-options.php` and calls the same helper.

## What is fixed, and what is not

The settings-save popup previously passed `error.error` directly to `alert`.
When that property is jQuery Migrate's compatibility method, the dialog displays
function source rather than a failure message. The failure callback now formats
only explicit string messages from known payload shapes, otherwise uses a safe
fallback, and restores the save controls without clearing unsaved state.

Nikki previously mapped JSON parsing and downstream processing exceptions to
“network request failed.” It now distinguishes rejected fetches, response-read
failures, non-JSON responses, HTTP failures, application rejection, invalid
success schemas, and unexpected processing exceptions. HTTP failure cannot
become a successful sync merely because its body says `success: true`. Raw
response bodies and exception details are not shown, logged, or persisted.

These local changes improve diagnostics and fix a reproducible empty-history
warning, but **do not prove or resolve the user's actual upstream sync failure**.
Offline fixtures establish:

- `inc/nikki-sync.php:283-304` distinguishes transport failure, non-200 upstream
  status, malformed JSON, API rejection, and incomplete profile data. The
  response body and token are not exposed. The fixtures verify classification;
  they do not establish which case occurred in the live request.
- `inc/nikki-sync.php:137-147` initializes
  `$gacha_by_pool_rarity_result` before processing draw history. The empty-history
  fixture verifies no PHP warning occurs and private state is not written.
- There is no captured authenticated admin-AJAX response, upstream POST result,
  or server log tied to the real click in this local-only verification. Expired
  credentials, proxy/WAF responses, timeouts, and other runtime failures remain
  unproven possibilities.

No production sync/save, server access, deployment, Redis operation, commit,
or push is required by these tests.
