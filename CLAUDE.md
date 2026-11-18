# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`jbaron/sitemapcrawler` is a small TYPO3 extension (namespace `Jbaron\Sitemapcrawler\`, extension key `sitemapcrawler`) for cache warm-up: it reads a site's XML sitemap into a DB table, then requests those URLs a batch at a time. It is one of Jost's open-source extensions, so its remote is GitHub (`JostBaron/typo3-sitemapcrawler`), not Gerrit. All three Sites require it as `jbaron/sitemapcrawler: ^1.1`, so tag a release before a change can reach them.

The parent workspace `CLAUDE.md` (`../../CLAUDE.md`) covers the workspace-wide conventions.

## Commands

The extension has no build step, tests, or linter. `composer install` here only installs a vendor tree (`vendor/` and `public/` are gitignored) for IDE autocompletion. To exercise the code, run it inside a site's TYPO3 installation:

```bash
vendor/bin/typo3 sitemapcrawler:parse <sitemap-or-sitemap-index-url>
vendor/bin/typo3 sitemapcrawler:crawl <number>   # clamped to 1..100000
```

Both commands are tagged `schedulable: true`, so the TYPO3 scheduler can also run them.

## Architecture

There are two phases. Each has a console command (`Classes/Command/`) that delegates to `Service\SitemapCrawler`:

1. **Parse** (`updateSitemapData`): `Service\SitemapParser` downloads the URL and decides from the root element whether it is a `<urlset>` or a `<sitemapindex>`. It follows an index only one level deep (an index that points to another index throws). It yields the `<loc>` URLs. The crawler adds each URL it hasn't seen before as a `SitemapUrl` entity. At the end it **deletes every stored URL that was not in this sitemap run**. `SitemapUrlRepository` tracks this through an in-memory `allUrlsCache` and a `checkedUrls` set: `resetCheckedUrls()` → `existsEntryFor()` per URL → `deleteUncheckedUrls()`. Because of that, `SitemapCrawler` and the repository are stateful singletons for the length of one command run.
2. **Crawl** (`crawlUrls`): fetches the N entries with the oldest `last_crawled` and GETs each one with the PSR-18 client. It stores the status code (`SitemapUrl::STATUSCODE_FAILED = -1` when an exception is thrown), the request duration and the crawl timestamp. It sleeps 0.25 s between requests and persists every 10 entries. It returns a status-code → count histogram, which the command prints.

Persistence is plain Extbase. The table is `tx_jbaron_sitemapcrawler_domain_model_sitemapurl`, mapped in `Configuration/Extbase/Persistence/Classes.php`. Its TCA lives in `Configuration/TCA/` and the table is allowed on standard pages in `ext_tables.php`. If you add a field, update `ext_tables.sql`, the TCA, and the model's constructor and accessors together; recent history shows bugs from forgetting one of them.

All wiring is explicit in `Configuration/Services.yaml`, including the command names, which are passed as the `$name` constructor argument. A new service or command needs an entry there.

## Gotchas

- `composer.json` is the source of truth for supported versions (PHP `^7.4 || ^8.0 || ^8.1`, TYPO3 `^10.4 || ^11.5`). The `ext_emconf.php` constraints (TYPO3 10.4 / PHP 7.4 only) are stale. Keep the code PHP 7.4-compatible: no constructor promotion, enums, `readonly` or union types.
- Commit subjects use `[TASK]` / `[BUGFIX]` prefixes, as in the rest of the workspace. Never add `Co-Authored-By:` or any other AI-attribution trailer to commit messages.
