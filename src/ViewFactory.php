<?php

namespace DigitalZombies\KirbyFluidEngine;

use Kirby\Cms\App;
use Kirby\Filesystem\Dir;
use DigitalZombies\KirbyFluidEngine\Variables\KirbyVariableProvider;
use TYPO3Fluid\Fluid\Core\Cache\SimpleFileCache;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Builds and memoises the configured Fluid view for the current request.
 *
 * A RenderingContext creates a parser, a compiler, four template processors and
 * a ViewHelper resolver, and the TemplatePaths instance caches every resolved
 * file. Kirby instantiates Template objects far more often than it renders them
 * (Page::template(), Page::intendedTemplate(), Page::toArray()), so this is
 * built once per request instead of once per Template object.
 */
final class ViewFactory
{
    protected static TemplateView|null $view = null;

    public static function view(App $kirby): TemplateView
    {
        return static::$view ??= new TemplateView(static::createContext($kirby));
    }

    public static function context(App $kirby): KirbyRenderingContext
    {
        return static::view($kirby)->getRenderingContext();
    }

    /**
     * Drops the memoised view, e.g. when roots or options change between tests
     */
    public static function reset(): void
    {
        static::$view = null;
    }

    protected static function createContext(App $kirby): KirbyRenderingContext
    {
        $context = new KirbyRenderingContext();

        // Kirby has no controller/action concept; an empty controller name
        // skips the `templates/<Controller>/<action>.html` lookup entirely
        $context->setControllerName('');
        $context->setControllerAction('');

        $paths = $context->getTemplatePaths();
        $paths->setTemplateRootPaths(static::roots($kirby, 'templates'));
        $paths->setLayoutRootPaths(static::roots($kirby, 'layouts'));
        $paths->setPartialRootPaths(static::roots($kirby, 'partials'));

        if ($cache = static::cache($kirby)) {
            $context->setCache($cache);
        }

        // added last, so these shadow the built-in ViewHelpers of the same name
        $resolver = $context->getViewHelperResolver();
        $resolver->addNamespace('f', 'DigitalZombies\\KirbyFluidEngine\\ViewHelpers');
        $resolver->addNamespace('f', 'DigitalZombies\\KirbyFluidEngine\\ViewHelpers\\Kirby');
        $resolver->addNamespace('k', 'DigitalZombies\\KirbyFluidEngine\\ViewHelpers');
        $resolver->addNamespace('k', 'DigitalZombies\\KirbyFluidEngine\\ViewHelpers\\Kirby');

        $context->setAttribute(App::class, $kirby);
        $context->setVariableProvider(new KirbyVariableProvider());

        return $context;
    }

    /**
     * Absolute root paths for the given kind of template file.
     *
     * TemplatePaths checks the configured paths in reverse order, so the
     * defaults come first and configured overrides win.
     */
    protected static function roots(App $kirby, string $kind): array
    {
        $defaults = match ($kind) {
            'templates' => [$kirby->root('templates')],
            'layouts'   => [$kirby->root('site') . '/layouts'],
            'partials'  => [$kirby->root('snippets'), $kirby->root('site') . '/partials'],
        };

        $configured = (array)$kirby->option('digital-zombies.kirby-fluid-engine.' . $kind, []);
        $roots      = [...$defaults, ...$configured];

        return array_values(array_map(
            fn (string $root): string => rtrim($root, '/') . '/',
            array_filter($roots, fn (string $root): bool => is_dir($root) === true)
        ));
    }

    /**
     * Fluid compiles templates to PHP classes. Without a cache every request
     * re-parses every template, layout and partial.
     */
    protected static function cache(App $kirby): SimpleFileCache|null
    {
        $option = $kirby->option('digital-zombies.kirby-fluid-engine.cache');

        if ($option === false) {
            return null;
        }

        // default: on, except while debugging
        if ($option === null && $kirby->option('debug', false) === true) {
            return null;
        }

        $root = is_string($option) === true
            ? $option
            : $kirby->root('cache') . '/fluid';

        Dir::make($root);

        return new SimpleFileCache($root);
    }
}
