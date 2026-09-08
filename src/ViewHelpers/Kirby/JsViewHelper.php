<?php

namespace DigitalZombies\KirbyFluidEngine\ViewHelpers\Kirby;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Kirby's js() helper: <k:js src="{0: 'assets/js/index.js', 1: '@auto'}"/>
 */
class JsViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('src', 'mixed', 'A URL or an array of URLs', true);
        $this->registerArgument('options', 'mixed', 'Attributes for the script tag');
    }

    public function render(): string
    {
        return (string)js($this->arguments['src'], $this->arguments['options']);
    }
}
