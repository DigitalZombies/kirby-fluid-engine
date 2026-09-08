<?php

namespace DigitalZombies\KirbyFluidEngine\ViewHelpers\Kirby;

use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Kirby's svg() helper, embedding the file inline: <k:svg src="assets/icons/discord.svg"/>
 */
class SvgViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('src', 'string', 'Path to the SVG file', true);
    }

    public function render(): string
    {
        return (string)svg($this->arguments['src']);
    }
}
