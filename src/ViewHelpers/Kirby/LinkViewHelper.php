<?php

namespace DigitalZombies\KirbyFluidEngine\ViewHelpers\Kirby;

use Kirby\Toolkit\Html;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * URLs and link markup.
 *
 *   {k:link(to: 'favicon.ico')}                          -> absolute URL
 *   <k:link to="{page.parent}" params="{tag: tag}">…</k:link>  -> <a href="…">…</a>
 *   <k:link email="{page.email}"/>                       -> obfuscated mailto link
 *   <k:link tel="{page.phone}"/>
 *
 * `params` covers Kirby's URL parameters, which a Kirby query cannot express
 * because its array literals are lists, never maps.
 */
class LinkViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('to', 'mixed', 'A path, or any Kirby object with a url() method');
        $this->registerArgument('params', 'array', 'URL parameters', false, []);
        $this->registerArgument('email', 'string', 'Renders an obfuscated mailto link');
        $this->registerArgument('tel', 'string', 'Renders a tel link');
        $this->registerArgument('attr', 'array', 'Attributes for the a tag', false, []);
    }

    public function render(): string
    {
        $text = $this->renderChildren();
        $text = $text === null ? null : trim((string)$text);
        $attr = $this->arguments['attr'];

        if ($email = $this->arguments['email']) {
            return Html::email((string)$email, $text, $attr);
        }

        if ($tel = $this->arguments['tel']) {
            return Html::tel((string)$tel, $text, $attr);
        }

        $url = $this->url();

        if (empty($text) === true && $attr === []) {
            return $url;
        }

        return Html::a($url, $text, $attr);
    }

    protected function url(): string
    {
        $to      = $this->arguments['to'];
        $params  = $this->arguments['params'];
        $options = $params === [] ? null : ['params' => $params];

        if (is_object($to) === true && method_exists($to, 'url') === true) {
            return (string)$to->url($options);
        }

        return url($to === null ? null : (string)$to, $options);
    }
}
