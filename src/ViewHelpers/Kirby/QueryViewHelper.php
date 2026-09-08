<?php

namespace DigitalZombies\KirbyFluidEngine\ViewHelpers\Kirby;

use Kirby\Query\Query;
use DigitalZombies\KirbyFluidEngine\Variables\KirbyVariableProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Runs a Kirby query: {k:query(q: 'page.cover.crop(400,500).url')}
 *
 * A Fluid variable path can only reach zero-argument methods, so anything like
 * `crop(400, 500)`, `.or(page.title)` or `toDate('c')` has to go through
 * Kirby's query language instead.
 */
class QueryViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('q', 'string', 'Kirby query, e.g. page.children.listed.limit(3)', true);
    }

    public function render(): mixed
    {
        $query = $this->arguments['q'];

        static::guard($query);

        return Query::factory($query)->resolve(
            $this->renderingContext->getVariableProvider()->getAll()
        );
    }

    /**
     * Kirby's Query calls methods directly and never passes through
     * KirbyVariableProvider, so the denylist has to be applied here as well –
     * otherwise `<k:query q="page.delete"/>` would delete a page.
     */
    protected static function guard(string $query): void
    {
        // drop string literals first, so a denied word inside a quoted
        // argument does not trip the check
        $query = preg_replace('/([\'"]).*?\1/', '', $query);

        preg_match_all('/[a-zA-Z_][a-zA-Z0-9_]*/', $query, $matches);

        foreach ($matches[0] as $identifier) {
            if (KirbyVariableProvider::isDenied($identifier) === true) {
                throw new Exception(
                    'The method "' . $identifier . '" cannot be called from a template.',
                    1756900001
                );
            }
        }
    }
}
