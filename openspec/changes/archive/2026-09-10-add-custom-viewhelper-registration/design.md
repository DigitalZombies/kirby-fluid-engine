## Context

See proposal.md — Why. Relevant current state:

- `ViewFactory::createContext()` builds one memoised `TemplateView` per request and registers the
  four built-in namespace/PHP-namespace pairs with four `addNamespace()` calls.
- `index.php` resets that memo in `system.loadPlugins:after`, which is the first point where all
  Kirby plugins and the site config are available.
- Fluid 5.3's `ViewHelperResolver` exposes `addNamespace()` (additive, last wins) and
  `setNamespaces()` (replaces everything, including the default `f`). Local `{namespace …}` and
  `xmlns:` declarations are handled by the parser and already work today — they are a
  documentation gap, not a code gap.
- Kirby's `App::extend()` only dispatches extension types it knows, but every key of a plugin
  definition stays readable through `Kirby\Plugin\Plugin::extends()`. That is the seam for
  plugin-provided registrations.
- Compiled templates are cached by an identifier derived from the template path and its mtime
  (`TemplatePaths::createIdentifierForFile()`), which knows nothing about resolver namespaces.

## Goals / Non-Goals

**Goals:**

- One resolved namespace map per request, built from built-ins + plugin extensions + site config,
  with a single, predictable precedence order.
- Fail loudly at boot for malformed registrations.
- Compiled templates that cannot survive a registration change.

**Non-Goals:**

- Autoloading user ViewHelper classes. Kirby's `load` config option and plugin/site Composer
  autoloaders already cover this; the plugin only documents it.
- Accepting `ViewHelperResolverDelegateInterface` instances as registration values. Fluid marks
  that input variant as still moving, and a config array is a poor place for object instances.
- Registering individual ViewHelper classes under an alias (no TYPO3 equivalent).
- Dependency injection into ViewHelpers — Fluid instantiates them with `new`; Kirby has no
  container.

## Decisions

### One `setNamespaces()` call over repeated `addNamespace()`

A new `ViewHelperNamespaces` class resolves the complete map and hands it to
`ViewHelperResolver::setNamespaces()` once, re-declaring `TYPO3Fluid\Fluid\ViewHelpers` for `f`
because `setNamespaces()` drops the default.

Alternative considered: keep calling `addNamespace()` per entry. Rejected — `addNamespace($id,
null)` appends `null` to an existing identifier's PHP namespace array instead of marking it
ignored, so "ignore `f`-like identifiers" and "ignore an identifier a plugin registered" would
behave inconsistently. Owning the map also makes precedence explicit rather than emergent from
call order.

### Precedence: built-ins → plugins → site config, per identifier, appended

Within an identifier the PHP namespaces are concatenated in that order and Fluid tests them in
reverse, so the site wins over plugins, and plugins win over built-ins. A `null` value replaces
whatever an earlier source registered for that identifier — that is the only way to switch an
identifier off, and the site must be able to switch off a plugin's.

Plugin order follows `kirby()->plugins()` (Kirby's own load order). Two plugins claiming the same
identifier is a conflict Kirby cannot resolve for them; last loaded wins, and the site can pin it.

### Registrations resolved in `system.loadPlugins:after`, memoised

The existing hook calls `ViewHelperNamespaces::load($kirby)` right after `ViewFactory::reset()`:
that is where validation exceptions belong, and it keeps `createContext()` free of config
gathering. `ViewFactory` reads the memo and falls back to resolving on demand, so tests that
build a context without booting the hook still work. `ViewHelperNamespaces::reset()` mirrors
`ViewFactory::reset()`.

### Cache identifier carries a hash of the map

`KirbyTemplatePaths extends TemplatePaths` overrides the protected `createIdentifierForFile()` to
append a short `xxh3` hash of the resolved map, and `ViewFactory` installs it with
`setTemplatePaths()`. Templates, layouts and partials all route their identifiers through that
method, so one override covers everything.

Alternatives considered: (a) nest the cache directory under the hash — simpler, but leaves an
orphaned directory per registration change and silently doubles disk use; (b) document "clear the
cache after changing `viewHelpers`" — free, but the failure mode is a template that renders
last week's markup, which is exactly the class of bug this plugin should not introduce.

### Validation kept minimal

Identifier: non-empty, matches `/^[A-Za-z0-9.*]+$/` (the character set Fluid's tag patterns accept,
plus `*`). Value: `string`, `array` of non-empty strings, or `null`. Whether a PHP namespace
actually contains a given ViewHelper is left to Fluid's own unresolved-ViewHelper error, which
already names the tag and the classes it looked for — checking class existence at boot would mean
guessing class names for tags nobody wrote.

## Risks / Trade-offs

- **A user registration shadows a built-in `k:`/`f:` helper by accident** → precedence is
  documented, and the site keeps the last word; `f:`/`k:` are shipped-first so this requires the
  site to name `f` or `k` explicitly.
- **Cache hash makes every registration change a full recompile** → correct but slower on the
  first request after a config deploy; identical to the cost of clearing the cache manually.
- **Overriding a `protected` Fluid method couples us to `TemplatePaths` internals** → covered by a
  test that asserts two different maps yield different identifiers, so a Fluid upgrade that
  reworks identifier generation fails loudly.
- **Plugin identifier collisions** → last-loaded wins; documented, with the site override as the
  escape hatch.

## Migration Plan

Additive and backwards compatible: with no `viewHelpers` option and no plugin declaring `fluid`,
the resolved map equals today's four registrations. The cache identifier changes once on upgrade,
so the first request after deploying recompiles templates. Rollback is a version pin — no data or
config migration.
