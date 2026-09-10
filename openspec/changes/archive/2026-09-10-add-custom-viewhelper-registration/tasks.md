## 1. Namespace resolution

- [x] 1.1 Add `src/ViewHelperNamespaces.php` with the built-in map (`f`, `k` → Fluid's
      `ViewHelpers`, the plugin's `ViewHelpers` and `ViewHelpers\Kirby`) and a `resolve(App)`
      that returns it unchanged when nothing else is registered
- [x] 1.2 Read the site option `digital-zombies.kirby-fluid-engine.viewHelpers` and merge it per
      identifier — appended after the built-ins, `null` replacing earlier entries
- [x] 1.3 Collect the `fluid.viewHelpers` key from every `kirby()->plugins()` entry via
      `Plugin::extends()` and merge it between built-ins and site config
- [x] 1.4 Add `load(App)` / `reset()` memoisation so the map is resolved once per request
- [x] 1.5 Add a `hash()` accessor returning a short `xxh3` hash of the resolved map

## 2. Validation

- [x] 2.1 Validate identifiers against `/^[A-Za-z0-9.*]+$/` and reject empty ones, with an
      exception naming the identifier
- [x] 2.2 Validate values as `string`, list of non-empty strings, or `null`, with an exception
      naming the identifier and the offending value
- [x] 2.3 Confirm both exceptions surface during `system.loadPlugins:after`, not at render time

## 3. Wiring

- [x] 3.1 Replace the four `addNamespace()` calls in `ViewFactory::createContext()` with a single
      `setNamespaces(ViewHelperNamespaces::resolve($kirby))`
- [x] 3.2 Add the `viewHelpers` option default (`[]`) to `index.php` and call
      `ViewHelperNamespaces::reset()` + `load()` in the `system.loadPlugins:after` hook next to
      `ViewFactory::reset()`

## 4. Cache invalidation

- [x] 4.1 Add `src/KirbyTemplatePaths.php` extending `TemplatePaths`, overriding
      `createIdentifierForFile()` to append `ViewHelperNamespaces::hash()`
- [x] 4.2 Install it in `createContext()` with `setTemplatePaths()` before the root paths are set,
      and confirm template, layout and partial identifiers all carry the hash

## 5. Verification in the starter site

- [x] 5.1 Add a throwaway `Acme\Site\ViewHelpers\TeaserViewHelper` plus a `Format\CurrencyViewHelper`
      to the starter site, autoloaded through the site's `load()` helper, and render
      `<my:teaser/>` and `{my:format.currency(value: 12)}` from a template, a layout and a partial
- [x] 5.2 Verify precedence: site registration for `k` overrides `k:svg` while `k:query` keeps
      working; a site registration overrides a plugin registration for the same identifier
- [x] 5.3 Verify `'x' => null` and `'x*' => null` render matching tags verbatim
- [x] 5.4 Verify the local `{namespace my=…}` / `xmlns:my` declarations work and do not leak into a
      rendered partial — an undeclared identifier in the partial raises Fluid's
      `UnknownNamespaceException`
- [x] 5.5 Verify cache invalidation: render with caching on, change `viewHelpers`, render the
      unchanged template again and confirm the new resolution is used
- [x] 5.6 Remove the throwaway ViewHelpers and site config additions

## 6. Documentation

- [x] 6.1 Add a "Custom ViewHelpers" section to `docs/view-helpers.md`: writing a ViewHelper,
      naming and nesting rules, escaping, the `viewHelpers` option with all value shapes, the
      per-template declaration syntax, and precedence
- [x] 6.2 Document the `fluid.viewHelpers` plugin extension key for plugin authors, including
      identifier collisions and how a site overrides them
- [x] 6.3 Document autoloading in a Kirby site (the `load()` helper, Composer autoload for
      plugins)
- [x] 6.4 Add the `viewHelpers` option to the README option list and options table, and link the
      new docs section
