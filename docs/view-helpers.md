# Kirby ViewHelpers

Fluid resolves a variable path like `{page.title}` by calling zero-argument methods on Kirby
objects. That covers most of Kirby's API, but not all of it: `crop(400, 500)` takes arguments,
`css()` and `js()` build markup rather than return content, and a snippet needs to be rendered.
The `k:` namespace fills those gaps.

```html
<k:svg src="assets/icons/discord.svg"/>
{k:query(q: 'page.cover.crop(1200, 600).url')}
```

Every helper is available under both `k:` and `f:`, so `k:query` and `f:query` are the same
ViewHelper. Prefer `k:` — it makes clear at a glance which tags come from Kirby rather than Fluid.

## Overview

| ViewHelper  | Purpose                                             | Output   |
| ----------- | --------------------------------------------------- | -------- |
| `k:query`   | Run a Kirby query, for anything a path cannot reach | escaped  |
| `k:link`    | URLs, `<a>` tags, obfuscated mail and tel links     | raw      |
| `k:snippet` | Render a Kirby PHP snippet                          | raw      |
| `k:css`     | Kirby's `css()` helper                              | raw      |
| `k:js`      | Kirby's `js()` helper                               | raw      |
| `k:svg`     | Embed an SVG file inline                            | raw      |
| `f:debug`   | Kirby-aware replacement for Fluid's `f:debug`       | raw      |

"Raw" means the helper's own markup is not escaped — see [Escaping](#escaping).

## `k:query`

Runs a [Kirby query](https://getkirby.com/docs/guide/blueprints/query-language). A Fluid variable
path can only call methods without arguments, so anything else goes through here: `crop(400, 500)`,
`.or(page.title)`, `toDate('c')`, `limit(3)`.

| Argument | Type     | Required | Description                    |
| -------- | -------- | -------- | ------------------------------ |
| `q`      | `string` | yes      | The query, e.g. `page.children.listed.limit(3)` |

The query resolves against the template's variables, so any variable in scope — including a loop
variable — can be its starting point:

```html
<f:for each="{page.children.listed}" as="album">
  <img src="{k:query(q: 'album.cover.resize(1024, 1024).url')}" alt="{album.cover.alt}">
</f:for>
```

Escape nested quotes with a backslash, since the query itself is a Fluid string:

```html
<time datetime="{k:query(q: 'page.date.toDate(\'c\')')}">{page.date}</time>
```

A query can return any value, not just strings — an object or a collection works as the subject of
a loop or a further path:

```html
<f:for each="{k:query(q: 'page.children.listed.limit(3)')}" as="note">
  <h2>{note.title}</h2>
</f:for>
```

Output is escaped. When a query returns HTML, pipe it through `f:format.raw`:

```html
{k:query(q: 'note.text.toBlocks.excerpt(280)') -> f:format.raw()}
```

Writing methods are refused. `<k:query q="page.delete"/>` throws rather than deleting the page —
the same denylist that guards variable paths applies here, because Kirby's query language calls
methods directly. See the [`deny` option](../README.md#deny).

## `k:link`

URLs and link markup.

| Argument | Type    | Required | Description                                        |
| -------- | ------- | -------- | -------------------------------------------------- |
| `to`     | `mixed` | no       | A path, or any Kirby object with a `url()` method  |
| `params` | `array` | no       | Kirby URL parameters                               |
| `email`  | `string`| no       | Renders an obfuscated `mailto:` link               |
| `tel`    | `string`| no       | Renders a `tel:` link                              |
| `attr`   | `array` | no       | Attributes for the `<a>` tag                       |

With no content and no `attr`, it returns a bare URL — useful in an attribute:

```html
<link rel="shortcut icon" type="image/x-icon" href="{k:link(to: 'favicon.ico')}">
```

Give it content or attributes and it renders an `<a>` tag instead. `to` accepts a Kirby object
directly, and `params` covers Kirby's URL parameters — which a query cannot express, because Fluid
array literals are lists, never maps:

```html
<f:for each="{page.tags.split}" as="tag">
  <k:link to="{page.parent}" params="{tag: tag}">{tag}</k:link>
</f:for>
```

`email` renders through Kirby's obfuscation, so the address does not appear in the source as plain
text. Both `email` and `tel` use the element's content as the link text when it has any, and the
address itself when it does not:

```html
<p><k:link email="{page.email}"/></p>
<p><k:link tel="{page.phone}"/></p>
<p><k:link email="{page.email}" attr="{class: 'button'}">Write to us</k:link></p>
```

## `k:snippet`

Renders a Kirby PHP snippet — the way to reuse an existing `site/snippets/*.php` file from a Fluid
template. For Fluid-to-Fluid reuse, use `f:render` with a partial instead.

| Argument | Type     | Required | Description                        |
| -------- | -------- | -------- | ---------------------------------- |
| `name`   | `string` | yes      | Snippet name, without the extension |
| `data`   | `array`  | no       | Variables passed to the snippet     |

Only what you pass in `data` reaches the snippet; the template's own variables are not forwarded
automatically. Given a `site/snippets/image.php` that expects `src`, `alt` and an optional `ratio`:

```html
<f:variable name="src" value="{k:query(q: 'image.resize(800).url')}"/>
<k:snippet name="image" data="{src: src, alt: image.alt, ratio: '3/2'}"/>
```

Nested snippets work as usual, so `name="blocks/image"` resolves
`site/snippets/blocks/image.php`.

## `k:css`

Kirby's [`css()`](https://getkirby.com/docs/reference/templates/helpers/css) helper.

| Argument  | Type    | Required | Description                                       |
| --------- | ------- | -------- | ------------------------------------------------- |
| `href`    | `mixed` | yes      | A URL, or an array of URLs                        |
| `options` | `mixed` | no       | Attributes for the `<link>` tag, or a media string |

Pass an array to emit one `<link>` per stylesheet from a single tag. Fluid has no list literal, so
use numeric keys — that is what the `{0: …, 1: …}` form below is doing. `@auto` adds Kirby's
automatic stylesheet for the current template, and is skipped when there is none:

```html
<k:css href="{0: 'assets/css/lightbox.css', 1: 'assets/css/index.css', 2: '@auto'}"/>
```

A single file, with a media query:

```html
<k:css href="assets/css/print.css" options="print"/>
```

## `k:js`

Kirby's [`js()`](https://getkirby.com/docs/reference/templates/helpers/js) helper. Mirrors `k:css`,
with `src` in place of `href`, and one `<script>` per URL when given an array.

| Argument  | Type    | Required | Description                          |
| --------- | ------- | -------- | ------------------------------------ |
| `src`     | `mixed` | yes      | A URL, or an array of URLs           |
| `options` | `mixed` | no       | Attributes for the `<script>` tag    |

```html
<k:js src="{0: 'assets/js/lightbox.js', 1: 'assets/js/index.js', 2: '@auto'}"/>
<k:js src="assets/js/analytics.js" options="{defer: 'defer'}"/>
```

Write a boolean attribute out in full, as `{defer: 'defer'}` above. Fluid reads an unquoted `true`
inside an array literal as a variable name, not as a boolean, so `{defer: true}` would silently
resolve to nothing.

## `k:svg`

Kirby's [`svg()`](https://getkirby.com/docs/reference/templates/helpers/svg) helper, embedding the
file's contents inline so it can be styled with CSS.

| Argument | Type     | Required | Description           |
| -------- | -------- | -------- | --------------------- |
| `src`    | `string` | yes      | Path to the SVG file  |

```html
<a href="https://chat.getkirby.com" aria-label="Chat with us on Discord">
  <k:svg src="assets/icons/discord.svg"/>
</a>
```

## `f:debug`

The plugin replaces Fluid's own `f:debug` with a Kirby-aware version. Use it as you always would:

```html
<f:debug>{page}</f:debug>
<f:debug title="Cover">{page.cover}</f:debug>
```

The replacement exists because the original is unsafe on Kirby objects. It enumerates every
property by reflection — including static ones, which raises "Accessing static property … as non
static" on Kirby models — and dumping `{kirby}` would print `App::$options`, i.e. the license key
and every credential in your config.

This version prints a short summary for the objects you actually want to inspect — pages, files,
fields, content, collections, templates — reads only public instance properties, and reports the
Kirby instance as just its URL and version.

## Escaping

`k:query` escapes its output, so a field containing `&` or `<` is safe to print. Everything a query
returns as HTML needs `-> f:format.raw()`, as above.

The other helpers build markup and therefore emit it unescaped. They are safe with content you
control, but treat their arguments as you would any raw output: an `attr` value or a URL that comes
from user input should be validated before it reaches them.

## Custom ViewHelpers

Anything a variable path and the shipped helpers cannot express belongs in a ViewHelper of your
own. A ViewHelper is a class extending Fluid's `AbstractViewHelper`, named `<Name>ViewHelper` and
placed in a PHP namespace you register:

```php
<?php
// site/plugins/acme/src/ViewHelpers/TeaserViewHelper.php

namespace Acme\Site\ViewHelpers;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class TeaserViewHelper extends AbstractViewHelper
{
    // the helper's own markup is not escaped; its arguments still are
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('page', 'object', 'The page to tease', true);
        $this->registerArgument('level', 'int', 'Heading level', false, 2);
    }

    public function render(): string
    {
        $page  = $this->arguments['page'];
        $level = $this->arguments['level'];

        return '<a href="' . $page->url() . '"><h' . $level . '>' . $page->title()->html() . '</h' . $level . '></a>';
    }
}
```

The tag name is the class name without the `ViewHelper` suffix, lowercased: `<my:teaser/>`.
Sub-namespaces become dots, so `Acme\Site\ViewHelpers\Format\CurrencyViewHelper` is
`{my:format.currency(value: 12)}`.

Leave `$escapeOutput` alone (it defaults to `true`) whenever the helper returns text rather than
markup — see [Escaping](#escaping). Arguments are always escaped on output regardless.

### Registering a namespace

Map an identifier to one or more PHP namespaces under the `viewHelpers` option:

```php
// site/config/config.php
return [
    'digital-zombies.kirby-fluid-engine' => [
        'viewHelpers' => [
            'my'   => 'Acme\Site\ViewHelpers',
            'base' => ['Acme\Base\ViewHelpers', 'Acme\Site\ViewHelpers'],
            'x'    => null,
            'sw*'  => null,
        ],
    ],
];
```

| Value                | Effect                                                                    |
| -------------------- | ------------------------------------------------------------------------- |
| a PHP namespace      | ViewHelpers are looked up in that namespace                                |
| an array of them     | Looked up in reverse order — the last entry wins for a duplicate name      |
| `null`               | The identifier is not Fluid; matching tags are output verbatim             |

An identifier may contain letters, digits, dots and `*`. The `*` wildcard only makes sense with
`null`: `'sw*' => null` leaves `<swiper:slide/>` and `<switch:case/>` in the output untouched,
which is how you keep another templating syntax in your markup out of Fluid's way.

Registrations apply to templates, layouts and partials alike. A malformed identifier or value
throws while Kirby boots; a tag whose ViewHelper class does not exist fails at render time with
Fluid's "could not be resolved" error, which names both.

### Extending `k:` and `f:`

`k` and `f` can be registered like any other identifier, which adds to them rather than replacing
them. Because the site is applied last, a class of yours wins over the shipped one of the same
name:

```php
'viewHelpers' => [
    'k' => 'Acme\Site\ViewHelpers',
],
```

With an `Acme\Site\ViewHelpers\SvgViewHelper` in place, `<k:svg/>` is yours while `<k:query/>` and
every other shipped helper keeps working.

### Per-template namespaces

For a helper used in a single file, declare the namespace in the file instead of the config:

```html
{namespace my=Acme\Site\ViewHelpers}
<my:teaser page="{page}"/>
```

Or, if you prefer your editor to keep treating the file as HTML:

```html
<html xmlns:my="http://typo3.org/ns/Acme/Site/ViewHelpers" data-namespace-typo3-fluid="true">
```

A declaration applies only to the file that contains it — it does not carry into a rendered
partial, which needs its own.

### Autoloading

The plugin registers namespaces; loading their classes is up to you.

- **In a plugin**: add a `composer.json` with a PSR-4 `autoload` block, or `require` the files from
  the plugin's `index.php`.
- **In the site**: call Kirby's `load()` helper, which maps lowercased class names to files, above
  the config's return statement:

  ```php
  // site/config/config.php

  load([
      'acme\\site\\viewhelpers\\teaserviewhelper'           => __DIR__ . '/../viewhelpers/TeaserViewHelper.php',
      'acme\\site\\viewhelpers\\format\\currencyviewhelper' => __DIR__ . '/../viewhelpers/Format/CurrencyViewHelper.php',
  ]);

  return [
      // ...
  ];
  ```

  For more than a handful of classes, a `composer.json` in the site root with a PSR-4 mapping is
  less work to maintain.

### Registering namespaces from a plugin

A plugin can bring its own ViewHelpers along, so a site using it needs no configuration:

```php
// site/plugins/acme-shop/index.php
Kirby::plugin('acme/shop', [
    'fluid' => [
        'viewHelpers' => [
            'shop' => 'Acme\Shop\ViewHelpers',
        ],
    ],
]);
```

Templates can then use `<shop:cart/>` right away.

Precedence runs built-ins → plugins → site config, and within an identifier the last registered
PHP namespace wins. So a site can override a plugin's `<shop:cart/>` by registering `shop` itself,
and switch the identifier off entirely with `'shop' => null`. Two plugins claiming the same
identifier is a conflict Kirby cannot resolve: the one loaded last wins, and the site settles it
by registering the identifier explicitly.

Changing any registration invalidates the compiled template cache automatically — the cache
identifier includes a fingerprint of the resolved namespaces, so templates are recompiled on the
next request.
