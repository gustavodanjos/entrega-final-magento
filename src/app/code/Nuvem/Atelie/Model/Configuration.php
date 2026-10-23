<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\SelectionInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * @var SelectionInterface[]
     */
    private array $selections;

    /**
     * @param int $size
     * @param string|null $engraving
     * @param SelectionInterface[] $selections
     */
    public function __construct(
        private int $size = 0,
        private ?string $engraving = null,
        array $selections = []
    ) {
        $this->selections = self::filterSelections($selections);
    }

    /**
     * @inheritdoc
     */
    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @inheritdoc
     */
    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTamanho(): int
    {
        return $this->getSize();
    }

    /**
     * @inheritdoc
     */
    public function setTamanho(int $tamanho): static
    {
        return $this->setSize($tamanho);
    }

    /**
     * @inheritdoc
     */
    public function getEngraving(): ?string
    {
        return $this->engraving;
    }

    /**
     * @inheritdoc
     */
    public function setEngraving(?string $engraving): static
    {
        $this->engraving = $engraving;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getGravacao(): ?string
    {
        return $this->getEngraving();
    }

    /**
     * @inheritdoc
     */
    public function setGravacao(?string $gravacao): static
    {
        return $this->setEngraving($gravacao);
    }

    /**
     * @inheritdoc
     */
    public function getSelections(): array
    {
        return $this->selections;
    }

    /**
     * @inheritdoc
     */
    public function setSelections(array $selections): static
    {
        $this->selections = $this->filterSelections($selections);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getSelecoes(): array
    {
        return $this->getSelections();
    }

    /**
     * @inheritdoc
     */
    public function setSelecoes(array $selecoes): static
    {
        return $this->setSelections($selecoes);
    }

    /**
     * Filter and validate selections.
     *
     * @param SelectionInterface[] $selections
     * @return SelectionInterface[]
     * @throws \InvalidArgumentException
     */
    private function filterSelections(array $selections): array
    {
        foreach ($selections as $selection) {
            if (!$selection instanceof SelectionInterface) {
                throw new \InvalidArgumentException(
                    'Every selection must be an instance of SelectionInterface.'
                );
            }
        }

        return array_values($selections);
    }
}
