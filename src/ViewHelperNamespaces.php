<?php

namespace DigitalZombies\KirbyFluidEngine;

use Kirby\Cms\App;
use Kirby\Exception\InvalidArgumentException;

/**
 * Resolves the ViewHelper namespaces available to every template, layout and
 * partial: the ones this plugin ships, the ones other Kirby plugins register
 * through their `fluid` extension key, and the ones the site configures.
 *
 * The complete map is handed to ViewHelperResolver::setNamespaces() in one go
 * rather than built up with addNamespace() calls, because addNamespace() has no
 * way of expressing "this identifier is not Fluid" for an identifier that
 * something else already registered: it appends the null to the existing list.
 */
final class ViewHelperNamespaces
{
    /**
     * Fluid drops its own default `f` namespace on setNamespaces(), so it is
     * re-declared here. The plugin's ViewHelpers come after it and therefore
     * shadow a Fluid ViewHelper of the same name.
     */
    public const BUILT_IN = [
        'f' => [
            'TYPO3Fluid\\Fluid\\ViewHelpers',
            'DigitalZombies\\KirbyFluidEngine\\ViewHelpers',
            'DigitalZombies\\KirbyFluidEngine\\ViewHelpers\\Kirby',
        ],
        'k' => [
            'DigitalZombies\\KirbyFluidEngine\\ViewHelpers',
            'DigitalZombies\\KirbyFluidEngine\\ViewHelpers\\Kirby',
        ],
    ];

    /**
     * Identifiers are written into template tags, so they are limited to what
     * Fluid's tag patterns accept, plus `*` for the ignore wildcard
     */
    public const IDENTIFIER_PATTERN = '/^[A-Za-z0-9.*]+$/';

    /** @var array<string, string[]|null>|null */
    protected static array|null $namespaces = null;

    /**
     * Resolves and memoises the map. Called while Kirby boots, so an invalid
     * registration is reported there instead of from a template.
     *
     * @return array<string, string[]|null>
     */
    public static function load(App $kirby): array
    {
        return static::$namespaces = static::build($kirby);
    }

    /**
     * @return array<string, string[]|null>
     */
    public static function resolve(App $kirby): array
    {
        return static::$namespaces ??= static::build($kirby);
    }

    public static function reset(): void
    {
        static::$namespaces = null;
    }

    /**
     * Fingerprint of a resolved map, for the compiled template cache identifier
     *
     * @param array<string, string[]|null>|null $namespaces
     */
    public static function hash(array|null $namespaces = null): string
    {
        return hash('xxh3', json_encode($namespaces ?? static::$namespaces ?? static::BUILT_IN));
    }

    /**
     * Built-ins first, then plugins, then the site: Fluid tests the PHP
     * namespaces of an identifier in reverse, so the site wins over a plugin
     * and a plugin wins over the built-ins.
     *
     * @return array<string, string[]|null>
     */
    protected static function build(App $kirby): array
    {
        $namespaces = static::BUILT_IN;

        foreach (static::plugins($kirby) as $registered) {
            $namespaces = static::merge($namespaces, $registered);
        }

        return static::merge($namespaces, static::option($kirby));
    }

    /**
     * The `fluid.viewHelpers` key of every loaded plugin, in Kirby's plugin
     * load order. Kirby ignores extension types it does not know, but keeps
     * them readable on the plugin object.
     *
     * @return array<int, mixed>
     */
    protected static function plugins(App $kirby): array
    {
        $registrations = [];

        foreach ($kirby->plugins() as $plugin) {
            $fluid = $plugin->extends()['fluid'] ?? null;

            if (is_array($fluid) === false) {
                continue;
            }

            if (array_key_exists('viewHelpers', $fluid) === true) {
                $registrations[$plugin->name()] = $fluid['viewHelpers'];
            }
        }

        return $registrations;
    }

    protected static function option(App $kirby): mixed
    {
        return $kirby->option('digital-zombies.kirby-fluid-engine.viewHelpers', []);
    }

    /**
     * @param array<string, string[]|null> $namespaces
     * @return array<string, string[]|null>
     */
    protected static function merge(array $namespaces, mixed $registrations): array
    {
        if ($registrations === null || $registrations === []) {
            return $namespaces;
        }

        if (is_array($registrations) === false) {
            throw new InvalidArgumentException(
                'The Fluid `viewHelpers` registration must be an array of namespace identifiers, ' .
                get_debug_type($registrations) . ' given'
            );
        }

        foreach ($registrations as $identifier => $phpNamespaces) {
            static::validateIdentifier($identifier);

            // null switches the identifier off entirely, whatever an earlier
            // source registered for it — that is how a site opts out of a
            // plugin's namespace, or marks `<x:foo/>` as not being Fluid
            if ($phpNamespaces === null) {
                $namespaces[$identifier] = null;
                continue;
            }

            $namespaces[$identifier] = array_values(array_unique([
                ...$namespaces[$identifier] ?? [],
                ...static::validateNamespaces($identifier, $phpNamespaces),
            ]));
        }

        return $namespaces;
    }

    protected static function validateIdentifier(mixed $identifier): void
    {
        if (is_string($identifier) === false || preg_match(static::IDENTIFIER_PATTERN, $identifier) !== 1) {
            throw new InvalidArgumentException(
                '"' . $identifier . '" is not a valid Fluid ViewHelper namespace identifier: ' .
                'expected letters, digits, dots or the * wildcard'
            );
        }
    }

    /**
     * @return string[]
     */
    protected static function validateNamespaces(string $identifier, mixed $phpNamespaces): array
    {
        $phpNamespaces = is_array($phpNamespaces) === true ? $phpNamespaces : [$phpNamespaces];

        foreach ($phpNamespaces as $phpNamespace) {
            if (is_string($phpNamespace) === false || trim($phpNamespace) === '') {
                throw new InvalidArgumentException(
                    'The Fluid ViewHelper namespace "' . $identifier . '" must be a PHP namespace, ' .
                    'an array of PHP namespaces or null, ' .
                    (is_string($phpNamespace) === true
                        ? 'the string "' . $phpNamespace . '"'
                        : get_debug_type($phpNamespace)) . ' given'
                );
            }
        }

        return array_map(fn (string $phpNamespace): string => trim($phpNamespace, " \t\n\r\0\x0B\\"), $phpNamespaces);
    }
}
