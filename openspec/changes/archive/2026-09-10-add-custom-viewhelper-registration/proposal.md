## Why

Templates can only use the ViewHelper namespaces the plugin hardcodes (`f:` and `k:`). Anything
project-specific — a `<my:teaser/>` tag, a site-wide formatter, a ViewHelper shipped by another
Kirby plugin — has no way in, and no way to shadow a built-in helper. TYPO3 solves this with a
global namespace registry plus per-template `{namespace …}` declarations; Fluid 5 exposes the same
seams through `ViewHelperResolver`, so all that is missing is the Kirby-side wiring.

## What Changes

- New `viewHelpers` plugin option: a map of namespace identifier → PHP namespace(s), registered on
  the resolver after the built-ins so a user helper of the same name wins.
- Values may be a single PHP namespace string, an array of them (tested in reverse order, as in
  Fluid), or `null` to mark an identifier as "not Fluid" so tags like `<v-slot:foo>` pass through
  unparsed. Wildcard identifiers (`ven*`) are supported for the same purpose.
- Extending `f:` and `k:` is allowed and additive, matching TYPO3's behaviour for `f:`.
- New `fluid` plugin extension key, so a Kirby plugin can register its own ViewHelper namespaces
  without the site owner touching `config.php`. Site config is applied last and therefore wins.
- Invalid entries (non-alphanumeric identifier, non-string namespace, unloadable class namespace)
  raise a clear exception during plugin bootstrap instead of surfacing as a Fluid parser error.
- Documented: writing a custom ViewHelper, class autoloading in a Kirby site, the per-template
  `{namespace my=My\ViewHelpers}` / `xmlns:my` syntax that Fluid already supports, and the need to
  clear the Fluid cache after changing registrations.

## Capabilities

### New Capabilities

- `view-helper-registration`: how ViewHelper namespaces are declared, merged, ordered, validated
  and resolved for Fluid templates rendered by Kirby.

### Modified Capabilities

None — no spec exists yet for the ViewHelper namespaces the plugin registers today; the new
capability spec covers both the built-in namespaces and user registrations.

## Impact

- `src/ViewFactory.php`: namespace registration moves behind a resolvable list built from
  built-ins + plugin extensions + site config.
- `index.php`: new `viewHelpers` option default; `system.loadPlugins:after` collects the `fluid`
  extension key from `kirby()->plugins()`.
- `README.md` and `docs/view-helpers.md`: new section on custom ViewHelpers.
- No new dependencies; `typo3fluid/fluid` ^5.3 already provides everything used.
- Backwards compatible: with no `viewHelpers` option set, resolution is unchanged.
