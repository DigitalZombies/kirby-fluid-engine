# view-helper-registration Specification

## Purpose

Defines how ViewHelper namespaces become available to Fluid templates rendered by Kirby: the
namespaces the plugin ships, the ones a site or another Kirby plugin registers, how competing
registrations are ordered, and how bad registrations are reported.

## Requirements

### Requirement: Built-in ViewHelper namespaces

The engine SHALL make Fluid's own ViewHelpers and the plugin's Kirby ViewHelpers available to every
template, layout and partial without configuration, under the identifiers `f` and `k`. A plugin
ViewHelper SHALL take precedence over a Fluid ViewHelper of the same name.

#### Scenario: Fluid core ViewHelper

- **WHEN** a template uses `<f:for each="{items}" as="item">`
- **THEN** Fluid's `ForViewHelper` renders the tag

#### Scenario: Kirby ViewHelper under both identifiers

- **WHEN** a template uses `<k:svg src="assets/icons/logo.svg"/>` or `<f:svg src="assets/icons/logo.svg"/>`
- **THEN** both resolve to the same plugin ViewHelper and render identical output

#### Scenario: Plugin ViewHelper shadows a Fluid ViewHelper

- **WHEN** a template uses `<f:debug>{page}</f:debug>` and the plugin ships a `debug` ViewHelper
- **THEN** the plugin's ViewHelper renders, not Fluid's

### Requirement: Site-level namespace registration

The engine SHALL accept a map of namespace identifier to PHP namespace under the
`digital-zombies.kirby-fluid-engine.viewHelpers` option and register every entry for all
templates, layouts and partials. A value MAY be a single PHP namespace string or an array of PHP
namespace strings. When an identifier maps to several PHP namespaces, the one listed last SHALL
win for a ViewHelper name present in more than one of them.

#### Scenario: Single PHP namespace

- **GIVEN** `'viewHelpers' => ['my' => 'Acme\\Site\\ViewHelpers']`
- **WHEN** a template uses `<my:teaser page="{page}"/>`
- **THEN** `Acme\Site\ViewHelpers\TeaserViewHelper` renders the tag

#### Scenario: Nested ViewHelper name

- **GIVEN** `'viewHelpers' => ['my' => 'Acme\\Site\\ViewHelpers']`
- **WHEN** a template uses `{my:format.currency(value: 12)}`
- **THEN** `Acme\Site\ViewHelpers\Format\CurrencyViewHelper` renders the expression

#### Scenario: Several PHP namespaces for one identifier

- **GIVEN** `'viewHelpers' => ['my' => ['Acme\\Base\\ViewHelpers', 'Acme\\Site\\ViewHelpers']]`
- **AND** a `TeaserViewHelper` exists in both
- **WHEN** a template uses `<my:teaser/>`
- **THEN** `Acme\Site\ViewHelpers\TeaserViewHelper` renders it

#### Scenario: Registration available in layouts and partials

- **GIVEN** a registered `my` identifier
- **WHEN** a layout or a partial uses `<my:teaser/>`
- **THEN** the tag resolves the same way it does in a template

### Requirement: Extending the built-in namespaces

The engine SHALL allow the `f` and `k` identifiers to appear in a registration. Such a
registration SHALL extend the identifier rather than replace it, and the registered PHP namespace
SHALL take precedence over the built-in ones.

#### Scenario: Overriding a shipped Kirby ViewHelper

- **GIVEN** `'viewHelpers' => ['k' => 'Acme\\Site\\ViewHelpers']` and an `Acme\Site\ViewHelpers\SvgViewHelper`
- **WHEN** a template uses `<k:svg src="assets/icons/logo.svg"/>`
- **THEN** the site's ViewHelper renders the tag

#### Scenario: Unaffected built-in ViewHelpers

- **GIVEN** the registration above
- **WHEN** a template uses `<k:query q="page.title"/>`
- **THEN** the plugin's shipped `query` ViewHelper still renders it

### Requirement: Plugin-provided namespace registration

The engine SHALL let any Kirby plugin register ViewHelper namespaces through a `fluid` extension
key on its plugin definition, using the same value shapes as the site option. Registrations from
plugins SHALL apply without any site configuration. Where a site registration and a plugin
registration use the same identifier, the site registration SHALL take precedence.

#### Scenario: Plugin registers its own namespace

- **GIVEN** a plugin declaring `'fluid' => ['viewHelpers' => ['shop' => 'Acme\\Shop\\ViewHelpers']]`
- **WHEN** a template uses `<shop:cart/>`
- **THEN** `Acme\Shop\ViewHelpers\CartViewHelper` renders the tag

#### Scenario: Site overrides a plugin's ViewHelper

- **GIVEN** the plugin registration above
- **AND** `'viewHelpers' => ['shop' => 'Acme\\Site\\ViewHelpers']` in the site config
- **AND** a `CartViewHelper` in both PHP namespaces
- **WHEN** a template uses `<shop:cart/>`
- **THEN** the site's `CartViewHelper` renders it

### Requirement: Per-template namespace declaration

The engine SHALL support Fluid's in-template namespace declarations —
`{namespace my=Acme\Site\ViewHelpers}` and the `xmlns:my="http://typo3.org/ns/Acme/Site/ViewHelpers"`
form — for templates, layouts and partials. Such a declaration SHALL apply only to the file that
contains it.

#### Scenario: Declaration inside a template

- **WHEN** a template declares `{namespace my=Acme\Site\ViewHelpers}` and uses `<my:teaser/>`
- **THEN** the tag renders without any configuration change

#### Scenario: Declaration does not leak

- **GIVEN** a template declaring `{namespace my=Acme\Site\ViewHelpers}`
- **WHEN** a partial rendered from that template uses `<my:teaser/>` without declaring the namespace
- **THEN** the tag is not resolved as a ViewHelper

### Requirement: Ignoring namespace identifiers

The engine SHALL accept `null` as a registration value, marking that identifier as not belonging
to Fluid, so tags and inline expressions using it are passed through to the output unparsed. An
identifier MAY contain `*` as a wildcard so a group of identifiers is ignored at once. Identifiers
SHALL be matched with the same character set Fluid's parser allows in a tag namespace prefix
(letters, digits and dots).

#### Scenario: Ignored identifier passes through

- **GIVEN** `'viewHelpers' => ['x' => null]`
- **WHEN** a template contains `<x:widget id="1"/>`
- **THEN** the markup is rendered verbatim and no ViewHelper lookup is attempted

#### Scenario: Wildcard identifier

- **GIVEN** `'viewHelpers' => ['x*' => null]`
- **WHEN** a template contains `<xui:button/>` and `<xtool:panel/>`
- **THEN** both are rendered verbatim

### Requirement: Invalid registrations are reported

The engine SHALL reject an invalid registration with an exception naming the offending identifier
or value, raised while Kirby boots rather than during template rendering. A registration is
invalid when the identifier is not a non-empty string of letters, digits, dots and `*`, or when
the value is neither a string, an array of strings, nor `null`. Rendering a template
that uses a registered identifier for which no matching ViewHelper class can be found SHALL fail
with Fluid's unresolved-ViewHelper error, which names the identifier and the ViewHelper.

#### Scenario: Malformed identifier

- **GIVEN** `'viewHelpers' => ['my namespace' => 'Acme\\Site\\ViewHelpers']`
- **WHEN** Kirby boots
- **THEN** an exception is raised naming `my namespace` as an invalid ViewHelper namespace identifier

#### Scenario: Malformed value

- **GIVEN** `'viewHelpers' => ['my' => 42]`
- **WHEN** Kirby boots
- **THEN** an exception is raised naming the `my` identifier and its unsupported value

#### Scenario: Missing ViewHelper class

- **GIVEN** `'viewHelpers' => ['my' => 'Acme\\Site\\ViewHelpers']` and no `TeaserViewHelper` in it
- **WHEN** a template using `<my:teaser/>` is rendered
- **THEN** rendering fails with an error naming `<my:teaser>` as unresolvable

### Requirement: Registration changes invalidate compiled templates

Changing the set of registered ViewHelper namespaces SHALL NOT let a template compiled under the
previous set continue to be served. A template SHALL be recompiled after its registrations
change, whether or not the template file itself was modified.

#### Scenario: New registration after a cached render

- **GIVEN** caching is enabled and a template using `<my:teaser/>` was rendered while `my` was ignored
- **WHEN** `my` is registered to a PHP namespace and the unchanged template is rendered again
- **THEN** the ViewHelper renders instead of the verbatim markup

#### Scenario: Unchanged registration reuses the cache

- **WHEN** a template is rendered twice with no change to templates or registrations
- **THEN** the second render reuses the compiled template
