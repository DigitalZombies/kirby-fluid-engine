# Kirby Fluid Engine

Integrates the [Fluid template engine](https://github.com/TYPO3/Fluid) into [Kirby CMS](https://getkirby.com).

:warning: This project is under active development and some APIs might change until the first stable version.

The plugin replaces Kirby's `template` component, so `site/templates/*.html` files are rendered
by Fluid while everything else about Kirby stays the same. Variable paths resolve against Kirby's
API (`{page.title}`, `{page.children.listed}`), snippets become Fluid partials, and a `k:`
ViewHelper namespace exposes the Kirby helpers that a variable path cannot express.

## Requirements

- Kirby 5
- PHP 8.2 or higher, with the `mbstring` extension
- Composer — see [Installation](#installation)

## Installation

The plugin depends on `typo3fluid/fluid` and does not bundle it, so it must be installed with
Composer — the download and git submodule methods are not supported:

```
composer require digital-zombies/kirby-fluid-engine
```

Composer places the plugin in `site/plugins/kirby-fluid-engine`, installs Fluid into the site's
`vendor` directory and registers both in the site's autoloader.

## Usage

### Options
All options live under the `digital-zombies.kirby-fluid-engine` key in `site/config/config.php`:

```php
return [
    'digital-zombies.kirby-fluid-engine' => [
        'templates'   => [],
        'layouts'     => [],
        'partials'    => [],
        'cache'       => null,
        'usephp'      => true,
        'deny'        => [],
        'viewHelpers' => [],
    ],
];
```

### Writing Templates

Fluid templates are plain `.html` files. Kirby content objects stay first-class: `{page.title}`,
`{site.children.listed}` and `{page.cover.alt}` resolve straight through Kirby's API.

For example your page template would look something like this:

```html
<!-- site/templates/home.html -->
<f:layout name="Base"/>

<f:section name="Main">
  <h1>{page.title}</h1>

  <f:for each="{site.children.listed}" as="item">
    <a href="{item.url}">{item.title}</a>
  </f:for>
</f:section>
```

With a Base Layout define like this:

```html
<!-- site/layouts/Base.html -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{site.title} | {page.title}</title>
  <k:css href="{0: 'assets/css/prism.css', 1: 'assets/css/lightbox.css', 2: 'assets/css/index.css', 3: '@auto'}"/>
  <k:js src="{0: 'assets/js/prism.js', 1: 'assets/js/lightbox.js', 2: 'assets/js/index.js', 3: '@auto'}"/>
  <link rel="shortcut icon" type="image/x-icon" href="{k:link(to: 'favicon.ico')}">
</head>
<body>
  <!-- header content -->
  <main class="main">
    <f:render section="Main" arguments="{_all}"/>
  </main>
  <!-- footer content -->
</body>
</html>
```

### Fluid ViewHelpers

We only ship ViewHelpers that are part of the core Fluid library,
so you won't get all ViewHelpers that are available in TYPO3 at the moment.
To see which ViewHelpers are available, take a look at the [Fluid ViewHelper documentation](https://docs.typo3.org/other/typo3fluid/fluid/main/en-us/ViewHelpers/Fluid/Index.html).

We're considering porting more commonly used ViewHelpers in the future. But

### Kirby ViewHelpers

A variable path can only call Kirby methods that take no arguments. For everything else — queries
like `crop(400, 500)`, `css()` and `js()` tags, inline SVGs, PHP snippets — the plugin ships a `k:`
ViewHelper namespace:

```html
<k:svg src="assets/icons/discord.svg"/>
{k:query(q: 'page.cover.crop(1200, 600).url')}
<k:link to="{page.parent}" params="{tag: tag}">{tag}</k:link>
```

See [docs/view-helpers.md](docs/view-helpers.md) for every helper, its arguments and examples.

### Configure Root Paths

Additional absolute root paths, appended to the defaults. Non-existing directories are ignored.

| Option      | Default roots                        |
| ----------- | ------------------------------------ |
| `templates` | `site/templates`                     |
| `layouts`   | `site/layouts`                       |
| `partials`  | `site/snippets`, `site/partials`     |

Roots are searched in reverse order, so a configured path takes precedence over the defaults —
which is how a plugin's or a theme's templates can be overridden from the site:

```php
'digital-zombies.kirby-fluid-engine' => [
    'templates' => [__DIR__ . '/../theme/templates'],
    'partials'  => [__DIR__ . '/../theme/partials'],
],
```

Within a root, a template named `article` is looked up as `article.fluid.html`, `article.html`,
`article`, and their `ucfirst` variants. The same applies to layouts and partials, which are
resolved by the name passed to `<f:layout name="…"/>` and `<f:render partial="…"/>`.

### `cache`

Fluid compiles every template into a PHP class. Without a cache each request re-parses every
template, layout and partial.

| Value           | Effect                                                     |
| --------------- | ---------------------------------------------------------- |
| `null`          | Default: cache on, except while Kirby's `debug` mode is on |
| `true`          | Always cache, into `site/cache/fluid`                      |
| `false`         | Never cache                                                |
| absolute path   | Always cache, into that directory                          |

The default cache root follows Kirby's `cache` root, so a custom `roots.cache` is respected. The
directory is created if it does not exist. Clear it after deploying changed templates — or point
it somewhere that a deployment wipes:

```php
'digital-zombies.kirby-fluid-engine' => [
    'cache' => true,
],
```

### `usephp`

Whether to fall back to Kirby's plain PHP templates when no Fluid template of that name exists.
Enabled by default, which makes it possible to migrate a site template by template.

Rendering is delegated to Kirby's own `Template` class in that case, so template extensions and
snippet slots keep working. Set it to `false` to make a missing Fluid template render nothing
instead:

```php
'digital-zombies.kirby-fluid-engine' => [
    'usephp' => false,
],
```

### `deny`

Method names that templates must not call. Templates are developer-authored, but a typo like
`{page.delete}` would otherwise silently mutate content, so writing methods are blocked: a denied
path resolves to `null`, and a denied method inside `<k:query/>` throws.

Blocked out of the box are `clean`, `create`, `delete`, `duplicate`, `flush`, `impersonate`,
`logout`, `move`, `publish`, `purge`, `remove`, `save`, `unpublish`, `update` and `write`, plus
anything starting with `change`, `create`, `delete`, `update` or `write`.

This option adds to that list — it cannot shorten it. Use it for methods of your own models or
plugins that a template has no business calling:

```php
'digital-zombies.kirby-fluid-engine' => [
    'deny' => ['sendInvoice', 'syncStock'],
],
```

### `viewHelpers`

Your own ViewHelper namespaces, as a map of identifier to PHP namespace — the equivalent of
TYPO3's global namespace registry:

```php
'digital-zombies.kirby-fluid-engine' => [
    'viewHelpers' => [
        'my'  => 'Acme\\Site\\ViewHelpers',
        'sw*' => null,
    ],
],
```

`<my:teaser/>` then resolves to `Acme\Site\ViewHelpers\TeaserViewHelper`, and
`{my:format.currency(value: 12)}` to `Acme\Site\ViewHelpers\Format\CurrencyViewHelper`. A value
may also be an array of PHP namespaces, of which the last one wins for a duplicate ViewHelper
name, or `null` to declare that the identifier is not Fluid at all so matching tags are output
verbatim.

`k` and `f` may be registered too, which extends rather than replaces them: your ViewHelper wins
over the shipped one of the same name. A plugin can register its own namespaces through a `fluid`
extension key, and a template can declare one for itself with `{namespace my=Acme\Site\ViewHelpers}`.

Loading the classes is up to you — Kirby's `load` option or a Composer autoloader. See
[docs/view-helpers.md](docs/view-helpers.md#custom-viewhelpers) for writing a ViewHelper,
autoloading, precedence and the plugin extension key.

## License

This plugin is licensed under the [MIT License](LICENSE.md).

