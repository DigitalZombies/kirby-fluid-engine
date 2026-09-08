<?php

namespace DigitalZombies\KirbyFluidEngine\ViewHelpers\Kirby;

use Kirby\Cms\App;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders a Kirby snippet: <k:snippet name="header" data="{page: page}" />
 */
class SnippetViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'Snippet name', true);
        $this->registerArgument('data', 'array', 'Variables passed to the snippet', false, []);
    }

    public function render(): string
    {
        $kirby = $this->renderingContext->getAttribute(App::class);

        return (string)$kirby->snippet(
            $this->arguments['name'],
            $this->arguments['data'] ?? [],
            true
        );
    }
}
