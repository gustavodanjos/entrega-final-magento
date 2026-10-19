<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\SelectionInterface;

class Configuration implements ConfigurationInterface
{
    /** @var SelectionInterface[] */
    private array $selections;

    /**
     * @param SelectionInterface[] $selections
     */
    public function __construct(
        private int $size = 0,
        private ?string $engraving = null,
        array $selections = []
    ) {
        $this->selections = self::filterSelections($selections);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getEngraving(): ?string
    {
        return $this->engraving;
    }

    public function setEngraving(?string $engraving): static
    {
        $this->engraving = $engraving;

        return $this;
    }

    public function getSelections(): array
    {
        return $this->selections;
    }

    public function setSelections(array $selections): static
    {
        $this->selections = self::filterSelections($selections);

        return $this;
    }

    /**
     * @param SelectionInterface[] $selections
     * @return SelectionInterface[]
     * @throws \InvalidArgumentException when an item is not a SelectionInterface instance
     */
    private static function filterSelections(array $selections): array
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
