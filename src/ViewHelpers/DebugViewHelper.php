<?php

namespace DigitalZombies\KirbyFluidEngine\ViewHelpers;

use Kirby\Cms\App;
use Kirby\Content\Content;
use Kirby\Content\Field;
use Kirby\Cms\ModelWithContent;
use Kirby\Template\Template;
use Kirby\Toolkit\Collection;
use ReflectionObject;
use TYPO3Fluid\Fluid\ViewHelpers\DebugViewHelper as FluidDebugViewHelper;

/**
 * Kirby-aware replacement for `f:debug`.
 *
 * The shipped ViewHelper enumerates `ReflectionObject::getProperties()` – which
 * includes static properties – and reads each one back through
 * `StandardVariableProvider::getByPath()`, i.e. as `$object->$name`. On any
 * Kirby object that raises "Accessing static property … as non static", and
 * dumping `Kirby\Cms\App` would expose the license key and every credential in
 * `App::$options`.
 *
 * Registered as an additional PHP namespace for the `f` alias, so it shadows
 * the original while all other `f:` ViewHelpers keep resolving.
 */
class DebugViewHelper extends FluidDebugViewHelper
{
    protected static function getValuesOfNonScalarVariable(mixed $variable): array
    {
        if (is_array($variable) === true || $variable instanceof \ArrayObject) {
            return (array)$variable;
        }

        if (is_resource($variable) === true) {
            return stream_get_meta_data($variable);
        }

        if (is_object($variable) === false) {
            return [];
        }

        if ($summary = static::summarize($variable)) {
            return $summary;
        }

        if ($variable instanceof \DateTimeInterface) {
            return parent::getValuesOfNonScalarVariable($variable);
        }

        // core only handles \Iterator; Kirby collections are \IteratorAggregate
        if ($variable instanceof \Traversable) {
            return iterator_to_array($variable);
        }

        return static::reflect($variable);
    }

    /**
     * Compact, useful representations for Kirby objects instead of their internals
     */
    protected static function summarize(object $variable): array|null
    {
        if ($variable instanceof App) {
            // never dump App::$options – it holds the license key,
            // session secrets and any plugin credentials
            return [
                'class'   => $variable::class,
                'url'     => $variable->url(),
                'version' => $variable->version(),
            ];
        }

        if ($variable instanceof Field) {
            return [
                'class' => $variable::class,
                'key'   => $variable->key(),
                'value' => $variable->value(),
            ];
        }

        if ($variable instanceof Content) {
            return $variable->toArray();
        }

        if ($variable instanceof ModelWithContent) {
            $summary = [
                'class' => $variable::class,
                'id'    => $variable->id(),
            ];

            // not every model has these; going through __call would return
            // an empty content field instead
            foreach (['url', 'template'] as $method) {
                if (method_exists($variable, $method) === true) {
                    $summary[$method] = (string)$variable->$method();
                }
            }

            $summary['content'] = $variable->content()->toArray();

            return $summary;
        }

        if ($variable instanceof Collection) {
            return [
                'class' => $variable::class,
                'count' => $variable->count(),
                'keys'  => $variable->keys(),
            ];
        }

        if ($variable instanceof Template) {
            return [
                'class' => $variable::class,
                'name'  => $variable->name(),
                'type'  => $variable->type(),
                'file'  => $variable->file(),
            ];
        }

        return null;
    }

    /**
     * Reads instance properties through reflection instead of `$object->$name`,
     * skipping static ones entirely
     */
    protected static function reflect(object $variable): array
    {
        $output = [];

        foreach ((new ReflectionObject($variable))->getProperties() as $property) {
            if ($property->isStatic() === true) {
                continue;
            }

            $output[$property->getName()] = $property->isInitialized($variable) === true
                ? $property->getValue($variable)
                : null;
        }

        return $output;
    }
}
