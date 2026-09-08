<?php

namespace DigitalZombies\KirbyFluidEngine;

use Kirby\Cms\App;
use Kirby\Filesystem\F;
use Kirby\Template\Template;
use DigitalZombies\KirbyFluidEngine\Variables\KirbyVariableProvider;

/**
 * Kirby's `template` component: resolves and renders the Fluid template for a
 * template name, falling back to the plain PHP template of the same name.
 */
class TemplateComponent extends Template
{
    protected App $kirby;
    protected Template|null $php = null;
    protected string|null|false $resolved = false;

    public function __construct(App $kirby, string $name, string $type = 'html', string $defaultType = 'html')
    {
        parent::__construct($name, $type, $defaultType);
        $this->kirby = $kirby;
    }

    public function extension(): string
    {
        return 'html';
    }

    /**
     * Fluid template first, PHP template as fallback
     */
    public function file(): string|null
    {
        if ($this->resolved !== false) {
            return $this->resolved;
        }

        // resolveFileInPaths() already tries `<name>.fluid.html`, `<name>.html`,
        // `<name>` and their ucfirst variants, so the lowercased Kirby template
        // name still finds `Home.html` on a case sensitive filesystem
        $file = ViewFactory::context($this->kirby)
            ->getTemplatePaths()
            ->resolveTemplateFileForControllerAndActionAndFormat('', $this->name(), $this->type());

        if ($file === null && $this->usePhp() === true) {
            $file = $this->php()->file();
        }

        return $this->resolved = $file;
    }

    public function render(array $data = []): string
    {
        $file = $this->file();

        if ($file === null) {
            return '';
        }

        if (F::extension($file) === 'php') {
            return $this->php()->render($data);
        }

        $view    = ViewFactory::view($this->kirby);
        $context = $view->getRenderingContext();

        $context->getTemplatePaths()->setFormat($this->type());

        // the whole data array becomes the variable source instead of being
        // assigned key by key: StandardVariableProvider::add() rejects
        // identifiers starting with `_` and the reserved `null`/`true`/`false`,
        // which a content field or controller variable may well be called
        $context->setVariableProvider(new KirbyVariableProvider($data));

        return $view->render($this->name());
    }

    /**
     * The plain Kirby template for the same name, used for the PHP fallback.
     * Delegating keeps Kirby's extension registry and snippet slot handling.
     */
    protected function php(): Template
    {
        return $this->php ??= new Template($this->name(), $this->type(), $this->defaultType());
    }

    protected function usePhp(): bool
    {
        return $this->kirby->option('digital-zombies.kirby-fluid-engine.usephp', true) === true;
    }
}
