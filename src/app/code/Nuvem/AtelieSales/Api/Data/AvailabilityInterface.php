<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Api\Data;

/**
 * Public availability flags for one component.
 *
 * @api
 */
interface AvailabilityInterface
{
    /**
     * Return the component code.
     *
     * @return string
     */
    public function getComponente(): string;

    /**
     * Return whether the component has stock.
     *
     * @return bool
     */
    public function getDisponivel(): bool;

    /**
     * Return whether the component is within the last units threshold.
     *
     * @return bool
     */
    public function getUltimasUnidades(): bool;
}
