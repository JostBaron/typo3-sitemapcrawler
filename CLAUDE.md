# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`jbaron/sitemapcrawler` is a small TYPO3 extension (namespace `Jbaron\Sitemapcrawler\`, extension key `sitemapcrawler`) for cache warm-up: it reads a site's XML sitemap into a DB table, then requests those URLs a batch at a time. It is one of Jost's open-source extensions, so its remote is GitHub (`JostBaron/typo3-sitemapcrawler`), not Gerrit. All three Sites require it as `jbaron/sitemapcrawler: ^1.1`, so tag a release before a change can reach them.

The parent workspace `CLAUDE.md` (`../../CLAUDE.md`) covers the workspace-wide conventions.

## Commands

There is no build step or linter. Tests run in throw-away Docker containers via `Build/Scripts/runTests.sh`, the same setup as `fal_database`. The script starts TYPO3's `core-testing-php*` image and, for functional tests, a tmpfs database:

```bash
Build/Scripts/runTests.sh -s composerUpdate          # install dev dependencies (once, or after a -p change)
Build/Scripts/runTests.sh -s unit                    # unit tests, no database
Build/Scripts/runTests.sh                            # functional tests on PostgreSQL (as in production)
Build/Scripts/runTests.sh -d mariadb                 # functional tests on MariaDB
Build/Scripts/runTests.sh -- --filter crawlUrls      # a single test; arguments after -- go to phpunit
Build/Scripts/runTests.sh -s lint                    # php -l over all files
```

`-p 8.3` and other values pick the PHP version. Run `composerUpdate` with the same `-p` first, because the vendor tree depends on the PHP version it was installed with. The functional run takes about a minute, mostly because `crawlUrls()` sleeps 0.25 s per URL.

Unit tests (`Tests/Unit/`) cover `SitemapParser`. Functional tests (`Tests/Functional/`, on `typo3/testing-framework` 7) cover the crawler, the repository, the commands and the configuration against a real database. Both use `Tests/Fixtures/FakeHttpClient`, a PSR-18 client that answers from a URL → response map (404 for unknown URLs) and records requests, so no test touches the network. `AbstractFunctionalTestCase::createCrawler()` builds the services by hand with that client, because the DI container would inject the real one.

To exercise the code against a real site, run it inside a site's TYPO3 installation:

```bash
vendor/bin/typo3 sitemapcrawler:parse <sitemap-or-sitemap-index-url>
vendor/bin/typo3 sitemapcrawler:crawl <number>   # clamped to 1..100000
```

Both commands are tagged `schedulable: true`, so the TYPO3 scheduler can also run them.

## Architecture

There are two phases. Each has a console command (`Classes/Command/`) that delegates to `Service\SitemapCrawler`:

1. **Parse** (`updateSitemapData`): `Service\SitemapParser` downloads the URL and decides from the root element whether it is a `<urlset>` or a `<sitemapindex>`. It follows an index only one level deep (an index that points to another index throws). It yields the `<loc>` URLs. The crawler adds each URL it hasn't seen before as a `SitemapUrl` entity. At the end it **deletes every stored URL that was not in this sitemap run**. `SitemapUrlRepository` tracks this through an in-memory `allUrlsCache` and a `checkedUrls` set: `resetCheckedUrls()` → `existsEntryFor()` per URL → `deleteUncheckedUrls()`. Because of that, `SitemapCrawler` and the repository are stateful singletons for the length of one command run.
2. **Crawl** (`crawlUrls`): fetches the N entries with the oldest `last_crawled` and GETs each one with the PSR-18 client. It stores the status code (`SitemapUrl::STATUSCODE_FAILED = -1` when an exception is thrown), the request duration and the crawl timestamp. It sleeps 0.25 s between requests and persists every 10 entries. It returns a status-code → count histogram, which the command prints.

Persistence is plain Extbase. The table is `tx_jbaron_sitemapcrawler_domain_model_sitemapurl`, mapped in `Configuration/Extbase/Persistence/Classes.php`. Its TCA lives in `Configuration/TCA/`; `ctrl.security.ignorePageTypeRestriction` allows the records on standard pages. If you add a field, update `ext_tables.sql`, the TCA, and the model's constructor and accessors together; recent history shows bugs from forgetting one of them.

All wiring is explicit in `Configuration/Services.yaml`, including the command names, which are passed as the `$name` constructor argument. A new service or command needs an entry there.

## Gotchas

- Supported versions: TYPO3 `^12.4` only, on PHP 8.1 to 8.3. `composer.json` is the source of truth; keep `ext_emconf.php` in line with it.
- Keep the code free of deprecations:
  - The tests turn PHP's own deprecations into errors (`convertDeprecationsToExceptions` in `Build/phpunit/*.xml`).
  - `ConfigurationTest::tcaNeedsNoMigration()` fails when TYPO3 would have to migrate the TCA.
  - TYPO3 12 deprecations are not caught: most are marked in docblocks only, and the testing framework handles the `E_USER_DEPRECATED` notices of the rest itself. Check the `@deprecated` docblocks of the core APIs you call. For example, use `findOneBy(['url' => …])`, not the magic `findOneByUrl()`.
- The extension needs no `ext_localconf.php` or `ext_tables.php`. If you add one, guard it with `defined('TYPO3') || die();`, never `TYPO3_MODE`: TYPO3 12 removed that constant. The testing framework still defines `TYPO3_MODE`, so tests won't catch a guard that uses it.
- Extbase does not call the model's constructor when it loads records, and it leaves a property unset when the column is `NULL`. Typed model properties therefore need a default value; otherwise their getters throw "must not be accessed before initialization".
- Commit subjects use `[TASK]` / `[BUGFIX]` prefixes, as in the rest of the workspace. Never add `Co-Authored-By:` or any other AI-attribution trailer to commit messages.
