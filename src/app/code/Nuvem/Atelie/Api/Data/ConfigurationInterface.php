<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * Configuration of an atelie sneaker: size, optional engraving and one selection per group.
 *
 * The SKU travels outside the configuration, in line with the REST and GraphQL
 * contracts, which always send {sku, configuracao}.
 *
 * @api
 */
interface ConfigurationInterface
{
    /**
     * Get the selected size of the sneaker.
     *
     * @return int
     */
    public function getSize(): int;

    /**
     * Set the selected size of the sneaker.
     *
     * @param int $size
     * @return $this
     */
    public function setSize(int $size): static;

    /**
     * Get the engraving text.
     *
     * @return string|null null when the configuration has no engraving
     */
    public function getEngraving(): ?string;

    /**
     * Set the engraving text.
     *
     * @param string|null $engraving null when the configuration has no engraving
     * @return $this
     */
    public function setEngraving(?string $engraving): static;

    /**
     * Get the chosen components, one per group.
     *
     * @return \Nuvem\Atelie\Api\Data\SelectionInterface[]
     */
    public function getSelections(): array;

    /**
     * Set the chosen components, one per group.
     *
     * @param \Nuvem\Atelie\Api\Data\SelectionInterface[] $selections
     * @return $this
     * @throws \InvalidArgumentException when an item is not a SelectionInterface instance
     */
    public function setSelections(array $selections): static;
}
