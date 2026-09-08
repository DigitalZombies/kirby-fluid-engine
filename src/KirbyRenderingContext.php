<?php

namespace DigitalZombies\KirbyFluidEngine;

use DigitalZombies\KirbyFluidEngine\Variables\KirbyScopedVariableProvider;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\ScopedVariableProvider;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;

/**
 * The only seam for keeping local scopes Kirby-aware.
 *
 * ForViewHelper and friends build their scope inline and hand it to
 * setVariableProvider(), so this is where it can be exchanged. The original
 * global and local providers are carried over unchanged, because the
 * ViewHelper keeps its own reference to the local one and adds the loop
 * variables to it after this call.
 */
class KirbyRenderingContext extends RenderingContext
{
    public function setVariableProvider(VariableProviderInterface $variableProvider)
    {
        if ($variableProvider instanceof ScopedVariableProvider) {
            $variableProvider = new KirbyScopedVariableProvider(
                $variableProvider->getGlobalVariableProvider(),
                $variableProvider->getLocalVariableProvider(),
            );
        }

        parent::setVariableProvider($variableProvider);
    }
}
