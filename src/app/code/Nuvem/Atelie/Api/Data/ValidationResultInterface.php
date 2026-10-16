<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api\Data;

/**
 * Outcome of a configuration validation.
 *
 * A validation does not stop at the first problem: when the configuration is
 * invalid, every violation found is reported in getMessages().
 *
 * @api
 */
interface ValidationResultInterface
{
    /**
     * Tell whether the configuration is valid.
     *
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Get the error messages ready for the customer, one per violation.
     *
     * @return string[] empty when the configuration is valid
     */
    public function getMessages(): array;
}
