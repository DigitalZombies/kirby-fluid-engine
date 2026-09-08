<?php

namespace DigitalZombies\KirbyFluidEngine\Variables;

use Kirby\Toolkit\Collection;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Throwable;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;

/**
 * Resolves variable paths against Kirby objects.
 *
 * Fluid's StandardVariableProvider only knows array keys, ArrayAccess,
 * getX()/isX()/hasX() and public properties. Kirby's API is built on __call
 * ($page->title(), $page->children()), which none of those reach, so every
 * `{page.title}` would resolve to null without this provider.
 */
class KirbyVariableProvider extends StandardVariableProvider
{
    /**
     * Methods that must never be invoked from a template. Templates are
     * developer-authored, but a typo like `{page.delete}` would otherwise
     * silently mutate content.
     */
    public const DEFAULT_DENIED_METHODS = [
        'clean',
        'create',
        'delete',
        'duplicate',
        'flush',
        'impersonate',
        'logout',
        'move',
        'publish',
        'purge',
        'remove',
        'save',
        'unpublish',
        'update',
        'write',
    ];

    public static array $deniedMethods = self::DEFAULT_DENIED_METHODS;

    /**
     * Method name prefixes that must never be invoked from a template.
     */
    public static array $deniedPrefixes = [
        'change',
        'create',
        'delete',
        'update',
        'write',
    ];

    public function getByPath(string $path): mixed
    {
        return static::extract($this->variables, $this->resolveSubVariableReferences($path));
    }

    /**
     * Walks a dotted path over arrays and Kirby objects.
     *
     * Static so that KirbyScopedVariableProvider can reuse it without
     * inheriting the variable storage.
     */
    public static function extract(mixed $subject, string $path): mixed
    {
        foreach (explode('.', $path) as $segment) {
            if (
                (is_array($subject) === true && array_key_exists($segment, $subject) === true) ||
                ($subject instanceof \ArrayAccess && $subject->offsetExists($segment) === true)
            ) {
                $subject = $subject[$segment];
                continue;
            }

            if (is_object($subject) === false) {
                return null;
            }

            if ($subject instanceof ContainerInterface && $subject->has($segment) === true) {
                $subject = $subject->get($segment);
                continue;
            }

            // A declared method of the exact name wins over Fluid's
            // getX()/isX()/hasX() convention. Kirby has both `children()` and
            // `hasChildren()`, and `{page.children}` must not become a boolean.
            if (method_exists($subject, $segment) === true) {
                if (static::isDenied($segment) === true) {
                    return null;
                }

                $subject = static::invoke($subject, $segment);
                continue;
            }

            $accessor = static::accessorMethod($subject, $segment);

            if ($accessor !== null) {
                $subject = $subject->$accessor();
                continue;
            }

            if (static::isReadableProperty($subject, $segment) === true) {
                $subject = $subject->$segment;
                continue;
            }

            // Kirby collections are keyed by id: {pages.home}, {page.files.cover-jpg}.
            // Cms\Collection::__call only serves registered collection methods,
            // so the key lookup has to happen explicitly.
            if ($subject instanceof Collection) {
                $item = $subject->get($segment);

                if ($item !== null) {
                    $subject = $item;
                    continue;
                }
            }

            // __call: content fields, page and field methods
            if (static::isDenied($segment) === false && is_callable([$subject, $segment]) === true) {
                $subject = static::invoke($subject, $segment);
                continue;
            }

            return null;
        }

        return $subject;
    }

    /**
     * Returns the getX()/isX()/hasX() method for the segment, if any
     */
    protected static function accessorMethod(object $subject, string $segment): string|null
    {
        $suffix = ucfirst($segment);

        foreach (['get', 'is', 'has'] as $prefix) {
            if (method_exists($subject, $prefix . $suffix) === true) {
                return $prefix . $suffix;
            }
        }

        return null;
    }

    /**
     * Only public, non-static properties may be read.
     *
     * `property_exists()` alone – as used by the StandardVariableProvider – is
     * true for protected and for static properties as well. Reading either
     * raises a PHP error, which Kirby's debug error handler turns into a fatal.
     * `Kirby\Template\Template::$data` and `ModelWithContent::$kirby` are both
     * public static, so this is hit on entirely ordinary paths.
     */
    protected static function isReadableProperty(object $subject, string $segment): bool
    {
        if (property_exists($subject, $segment) === false) {
            return false;
        }

        try {
            $property = new ReflectionProperty($subject, $segment);
        } catch (Throwable) {
            return false;
        }

        return $property->isPublic() === true && $property->isStatic() === false;
    }

    protected static function invoke(object $subject, string $method): mixed
    {
        try {
            return $subject->$method();
        } catch (Throwable) {
            // missing required arguments or an unknown method: Fluid resolves
            // unknown paths to null rather than failing
            return null;
        }
    }

    public static function isDenied(string $method): bool
    {
        $method = strtolower($method);

        if (in_array($method, static::$deniedMethods, true) === true) {
            return true;
        }

        foreach (static::$deniedPrefixes as $prefix) {
            if (str_starts_with($method, $prefix) === true) {
                return true;
            }
        }

        return false;
    }
}
