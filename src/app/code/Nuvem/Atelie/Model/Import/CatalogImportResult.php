<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Import;

use Nuvem\Atelie\Api\Data\CatalogImportResultInterface;
use Nuvem\Atelie\Api\Data\CatalogImportRowErrorInterface;

/**
 * Class CatalogImportResult
 *
 * Implements CatalogImportResultInterface.
 */
class CatalogImportResult implements CatalogImportResultInterface
{
    /**
     * @param int $createdCount
     * @param int $updatedCount
     * @param int $refusedCount
     * @param bool $isSimulated
     * @param bool $isSuccessful
     * @param CatalogImportRowErrorInterface[] $errors
     * @param string[] $messages
     */
    public function __construct(
        private readonly int $createdCount = 0,
        private readonly int $updatedCount = 0,
        private readonly int $refusedCount = 0,
        private readonly bool $isSimulated = false,
        private readonly bool $isSuccessful = true,
        private readonly array $errors = [],
        private readonly array $messages = []
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    /**
     * @inheritdoc
     */
    public function getRefusedCount(): int
    {
        return $this->refusedCount;
    }

    /**
     * @inheritdoc
     */
    public function isSimulated(): bool
    {
        return $this->isSimulated;
    }

    /**
     * @inheritdoc
     */
    public function isSuccessful(): bool
    {
        return $this->isSuccessful;
    }

    /**
     * @inheritdoc
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @inheritdoc
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
