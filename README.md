# Speed Up - Page Cache

[![CI](https://github.com/nigrosimone/speed-up-page-cache/actions/workflows/ci.yml/badge.svg)](https://github.com/nigrosimone/speed-up-page-cache/actions/workflows/ci.yml)
[![WordPress plugin](https://img.shields.io/wordpress/plugin/v/speed-up-page-cache.svg)](https://wordpress.org/plugins/speed-up-page-cache/)
[![Active installs](https://img.shields.io/wordpress/plugin/installs/speed-up-page-cache.svg)](https://wordpress.org/plugins/speed-up-page-cache/)
[![Downloads](https://img.shields.io/wordpress/plugin/dt/speed-up-page-cache.svg)](https://wordpress.org/plugins/speed-up-page-cache/)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

A full-page cache for WordPress: the first visitor to a page pays for rendering it,
everyone after that gets a static HTML file.

**One-click install.** Activate it and it's on.

📦 [Get it on WordPress.org](https://wordpress.org/plugins/speed-up-page-cache/)

## What it does

Rendering a WordPress page means running PHP and querying MySQL. Under traffic, that's
where the RAM and CPU go — and the result is usually identical for every visitor.

This plugin saves the finished HTML and serves it directly on subsequent requests, before
WordPress loads at all:

```
first visit:   request → PHP → MySQL → render → save HTML → respond
after that:    request → read HTML file → respond          (WordPress never loads)
```

That last point is what makes a page cache different from every other kind: the saved file
is served from `advanced-cache.php`, a drop-in WordPress loads at the very beginning of
the request. PHP barely starts and MySQL is never touched.

## Who gets cached pages

Only visitors for whom the page is genuinely identical:

- not logged in
- haven't left a comment
- aren't viewing a password-protected post

Logged-in users, comment authors, POST requests, 404s, feeds and pages containing
`DONOTCACHEPAGE` always render normally.

## Setup

Activation does three things, and each one needs to be possible:

| Step | What it needs |
| --- | --- |
| Copy `advanced-cache.php` into `wp-content/` | `wp-content/` writable |
| Add `define( 'WP_CACHE', true );` to `wp-config.php` | `wp-config.php` writable |
| Create the cache directory | `wp-content/` writable |

If any step fails the plugin says so in the dashboard, with the manual instructions. It
never fails silently.

## Settings and purging

**Settings → Page Cache** offers:

- **Purge all caches**, purge a single post by ID, or purge a single URL
- **Cache exception URLs** — one per line, never cached
- **Scheduled purge** — hourly, twice daily, daily, weekly or monthly

The cache also purges itself when you publish or edit a post: that post, the blog page, and
the relevant taxonomy and pagination pages.

There's a `speed-up-page-cache-cacheable` filter to decide per-request whether a page
should be cached, and a `supc_save_config` action fired when settings are saved.

## Requirements

WordPress 6.0 or newer, PHP 7.0 or newer, and a writable `wp-content/` and `wp-config.php`.
Works with Apache and nginx.

## Installation

From your dashboard: **Plugins → Add New**, search for *Speed Up - Page Cache*, install and
activate.

Manually: upload the `speed-up-page-cache` folder to `/wp-content/plugins/` and activate it
from the **Plugins** menu.

## Development

This is the largest plugin of the family: about 2,100 lines across nine classes.

```bash
composer install        # also activates the git pre-commit hook

composer test           # PHPUnit
composer phpcs          # WordPress Coding Standards
composer phpcbf         # auto-fix coding style
composer lint           # php -l across every shipped file
composer compat         # PHP 7.0+ compatibility
composer check-version  # plugin header, Stable tag and changelog agree
```

Every check above also runs in CI on each pull request, across PHP 7.2 → 8.4, and all of
them block a merge. The one exception is WordPress Plugin Check: it needs Docker and npm,
so an infrastructure hiccup there must not fail a release.

The CI workflows and the helper commands come from
[`nigrosimone/wp-plugin-ci`](https://github.com/nigrosimone/wp-plugin-ci), shared by every
`speed-up-*` plugin so there's one copy to maintain instead of eight.

The test suite concentrates on the two riskiest things the plugin does: **editing
`wp-config.php`** — get that wrong and the site won't open — and **translating URLs into
cache paths**, where two different URLs landing on the same path means a visitor served
someone else's page. Both write real files into a sandbox rather than mocking.

### This plugin runs before WordPress

`advanced-cache.php` is loaded by `wp-settings.php` very early, and pulls in
`cache-manager.php` → `cache-utils.php`. At that point `WP_Filesystem` doesn't exist and
neither does `wp_parse_url()`. That's why `phpcs.xml.dist` excludes the sniffs that would
recommend them: following those recommendations here would be a fatal error, not a
best practice. Each exclusion carries its reason in the file.

For the same reason `SUPC_DROPIN` keeps its unprefixed name: the drop-in is *copied* into
`wp-content/` at activation and isn't regenerated on every plugin update, so renaming the
constant would make an updated plugin report the older drop-in as missing.

### Releases

**GitHub is the source of truth. The WordPress.org SVN repository is a publishing target,
written only by CI — never edit it by hand.**

**Actions → Prepare release → Run workflow**, filling in the version, `Tested up to` and
the changelog. The workflow opens a pull request with the version bump; merging it tags
the release, publishes to WordPress.org and creates a GitHub Release.

A weekly job compares the published SVN trunk against `main` and opens an issue if they
diverge.

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) — © Simone Nigro
