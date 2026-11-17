# Silverwind Site

Site-level features for Silverstripe CMS 6 sites on the
[Silverwind](https://github.com/marcd1983/silverwind) theme, alongside
[silverwind-blocks](https://github.com/marcd1983/silverwind-blocks) and
[silverwind-settings](https://github.com/marcd1983/silverwind-settings).
[silverwind-starter](https://github.com/marcd1983/silverwind-starter) sets
all of it up in one command.

| Feature | What it adds |
|---|---|
| **Services** | Service holder and service pages, and a Services block (featured, all or chosen; grid or carousel) |
| **Ribbon CTAs** | A library of scheduled call-to-action bands (CMS menu *Ribbon CTAs*), placed with a block or site-wide above the footer; pages can inherit, hide or swap it |
| **Testimonials, Partners** | Admin sections and blocks: quotes as a carousel or grid, partner logos or cards |
| **Scripts** | *Settings > Scripts*: analytics/tag snippets tied to a cookie consent group, loading only after consent (with Silverwind's consent gate) |
| **Search** | Site search that includes the text of a page's blocks, with excerpts |
| **SEO** | schema.org JSON-LD: Organization, WebSite with search, breadcrumbs |
| **Security headers** | nosniff, referrer and permissions policies, framing, HSTS, and a report-only Content-Security-Policy |
| **Form submissions** | A *Form Submissions* admin; antlion/store-location enquiries are logged there |
| **Page options** | Page image and description, child grid columns, hide title bar, open in new tab, mega menu layout |
| **Demo** | `sake tasks:seed-demo-site`: a complete demo site with Pixabay photos |

## Requirements

Silverstripe CMS 6, PHP 8.3+, the Silverwind theme, silverwind-blocks and
silverwind-settings. The site's own `Page` and `PageController` classes
(global namespace) get the extensions; `PageController::SearchForm()`
should return the block-aware search form:

```php
public function SearchForm()
{
    return \Antlion\SilverwindSite\Search\SiteSearchForm::forController($this);
}
```

Tailwind must scan this module (Silverwind's `src/css/silverwind.css`):

```css
@source "../../../../vendor/antlion/silverwind-site/templates";
@source "../../../../vendor/antlion/silverwind-site/src";
```

## Configuration

```yaml
# Content-Security-Policy: enforce once it reports clean
Antlion\SilverwindSite\Middleware\SecurityHeadersMiddleware:
  csp_enforce: true
```

Cookie groups (Analytics, Marketing, External) are added to
innoweb/silverstripe-cookie-consent's config; see `_config/config.yml`.

## Demo site

```sh
vendor/bin/sake tasks:seed-demo-site [--reset] [--refresh-images]
```

Builds "Fernbrook Landscapes": block-built pages, services, ribbon CTAs,
testimonials, partners, a demo analytics script, footer menus, brand colours
and, with antlion/store-location, three locations. Photos come from
[Pixabay](https://pixabay.com/) when `PIXABAY_API_KEY` is set, otherwise
generated placeholders; the chosen photos are recorded in
`demo/images.json`. Run it as the web user (`-u www-data` in Docker).

## Upgrading a site whose app/ had these classes

The tables are unchanged and `db:build` rewrites stored class names
(`classname_value_remapping` in `_config/config.yml`), so content carries
over. Remove the old classes and their config from `app/`, then
`sake db:build --flush`.

## Tests

```sh
vendor/bin/phpunit vendor/antlion/silverwind-site/tests
```

## License

BSD-3-Clause
