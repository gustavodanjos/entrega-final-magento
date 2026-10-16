<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * One component chosen for one group of a configuration.
 *
 * @api
 */
interface SelectionInterface
{
    /**
     * Get the group of the selection (e.g. "cabedal").
     *
     * @return string
     */
    public function getGroup(): string;

    /**
     * Set the group of the selection.
     *
     * @param string $group
     * @return $this
     */
    public function setGroup(string $group): static;

    /**
     * Get the chosen component (e.g. "CAB-TRICO-LAVANDA").
     *
     * @return string
     */
    public function getComponent(): string;

    /**
     * Set the chosen component.
     *
     * @param string $component
     * @return $this
     */
    public function setComponent(string $component): static;
}
