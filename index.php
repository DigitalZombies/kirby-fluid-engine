<?php

use Kirby\Cms\App as Kirby;
use DigitalZombies\KirbyFluidEngine\TemplateComponent;
use DigitalZombies\KirbyFluidEngine\ViewFactory;
use DigitalZombies\KirbyFluidEngine\Variables\KirbyVariableProvider;

Kirby::plugin('digital-zombies/kirby-fluid-engine', [
    'options' => [
        // additional root paths, appended to the defaults
        'templates' => [],
        'layouts'   => [],
        'partials'  => [],
        // true/false, or an absolute path; null = on unless debugging
        'cache'     => !kirby()->environment()->isLocal(),
        // fall back to .php templates when no Fluid template exists
        'usephp'    => true,
        // extra method names that templates must not call
        'deny'      => [],
    ],
    'components' => [
        'template' => function (Kirby $kirby, string $name, string $contentType = 'html', string $defaultType = 'html') {
            return new TemplateComponent($kirby, $name, $contentType, $defaultType);
        },
    ],
    'hooks' => [
        'system.loadPlugins:after' => function () {
            ViewFactory::reset();

            KirbyVariableProvider::$deniedMethods = array_values(array_unique([
                ...KirbyVariableProvider::DEFAULT_DENIED_METHODS,
                ...array_map('strtolower', (array)kirby()->option('digital-zombies.kirby-fluid-engine.deny', [])),
            ]));
        },
    ]
]);
