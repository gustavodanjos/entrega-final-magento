<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * Interface CatalogImportResultInterface
 *
 * Encapsulates the result and summary of a catalog import process.
 *
 * @api
 */
interface CatalogImportResultInterface
{
    /**
     * Get count of newly created components
     *
     * @return int
     */
    public function getCreatedCount(): int;

    /**
     * Get count of updated components
     *
     * @return int
     */
    public function getUpdatedCount(): int;

    /**
     * Get count of refused rows
     *
     * @return int
     */
    public function getRefusedCount(): int;

    /**
     * Whether the import was executed in simulation mode
     *
     * @return bool
     */
    public function isSimulated(): bool;

    /**
     * Check if import was successful (no refused rows and changes applied or simulated)
     *
     * @return bool
     */
    public function isSuccessful(): bool;

    /**
     * Get list of row validation errors
     *
     * @return CatalogImportRowErrorInterface[]
     */
    public function getErrors(): array;

    /**
     * Get list of informational messages
     *
     * @return string[]
     */
    public function getMessages(): array;
}
