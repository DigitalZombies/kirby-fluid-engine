<?php

namespace DigitalZombies\KirbyFluidEngine;

use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * Ties the compiled template cache to the registered ViewHelper namespaces.
 *
 * Fluid derives the cache identifier from the template path and its mtime, so
 * registering a ViewHelper namespace — which changes how the very same source
 * is parsed — would otherwise keep serving the template compiled before it.
 * Templates, layouts and partials all build their identifier here.
 */
class KirbyTemplatePaths extends TemplatePaths
{
    public function __construct(protected string $namespaceHash = '')
    {
    }

    protected function createIdentifierForFile(?string $pathAndFilename): string
    {
        return parent::createIdentifierForFile($pathAndFilename) . '_' . $this->namespaceHash;
    }
}
