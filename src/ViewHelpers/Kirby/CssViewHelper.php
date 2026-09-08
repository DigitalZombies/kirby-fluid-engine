<?php

namespace DigitalZombies\KirbyFluidEngine\ViewHelpers\Kirby;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Kirby's css() helper: <k:css href="{0: 'assets/css/index.css', 1: '@auto'}"/>
 */
class CssViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('href', 'mixed', 'A URL or an array of URLs', true);
        $this->registerArgument('options', 'mixed', 'Attributes for the link tag, or a media string');
    }

    public function render(): string
    {
        return (string)css($this->arguments['href'], $this->arguments['options']);
    }
}
