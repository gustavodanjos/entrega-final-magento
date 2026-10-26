<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * Interface CatalogImportRowErrorInterface
 *
 * Represents a validation error on a specific row during catalog import.
 *
 * @api
 */
interface CatalogImportRowErrorInterface
{
    /**
     * Get row number in the imported file (1-based, including header)
     *
     * @return int
     */
    public function getRowNumber(): int;

    /**
     * Get component code associated with the error
     *
     * @return string|null
     */
    public function getComponentCode(): ?string;

    /**
     * Get error reason description
     *
     * @return string
     */
    public function getReason(): string;
}
