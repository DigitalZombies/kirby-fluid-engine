<?php

namespace DigitalZombies\KirbyFluidEngine\Variables;

use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;

/**
 * Kirby-aware equivalent of Fluid's ScopedVariableProvider.
 *
 * `f:for`, `f:alias`, `f:cycle` and `f:groupedFor` create their local scope with
 * a hard-coded StandardVariableProvider, so `{item.title}` inside a loop would
 * never reach Kirby's __call, and `{item.url}` would fatal on the protected
 * `Page::$url`. ScopedVariableProvider is final, so instead of subclassing it
 * this reimplements the same delegation and resolves paths through
 * KirbyVariableProvider. KirbyRenderingContext swaps it in, keeping the very
 * same global and local provider instances the ViewHelper still writes to.
 */
final class KirbyScopedVariableProvider extends KirbyVariableProvider
{
    public function __construct(
        protected VariableProviderInterface $globalVariables,
        protected VariableProviderInterface $localVariables,
    ) {
    }

    public function getGlobalVariableProvider(): VariableProviderInterface
    {
        return $this->globalVariables;
    }

    public function getLocalVariableProvider(): VariableProviderInterface
    {
        return $this->localVariables;
    }

    public function add(string $identifier, mixed $value): void
    {
        $this->globalVariables->add($identifier, $value);
        $this->localVariables->add($identifier, $value);
    }

    public function remove(string $identifier): void
    {
        $this->globalVariables->remove($identifier);
        $this->localVariables->remove($identifier);
    }

    public function setSource(mixed $source): void
    {
        $this->globalVariables->setSource($source);
    }

    public function getSource(): array
    {
        return $this->getAll();
    }

    public function getAll(): array
    {
        return [
            ...$this->globalVariables->getAll(),
            ...$this->localVariables->getAll(),
        ];
    }

    public function exists(string $identifier): bool
    {
        return $this->localVariables->exists($identifier) ||
               $this->globalVariables->exists($identifier);
    }

    public function get(string $identifier): mixed
    {
        return $this->getByPath($identifier);
    }

    public function getByPath(string $path): mixed
    {
        $path       = $this->resolveSubVariableReferences($path);
        $identifier = explode('.', $path, 2)[0];

        $source = $this->localVariables->exists($identifier) === true
            ? $this->localVariables->getAll()
            : $this->globalVariables->getAll();

        return static::extract($source, $path);
    }

    public function getAllIdentifiers(): array
    {
        return array_values(array_unique([
            ...$this->globalVariables->getAllIdentifiers(),
            ...$this->localVariables->getAllIdentifiers(),
        ]));
    }

    public function getScopeCopy(array|\ArrayAccess $variables): VariableProviderInterface
    {
        // local variables are irrelevant for partials and sections, they get
        // every variable passed explicitly
        return $this->globalVariables->getScopeCopy($variables);
    }
}
