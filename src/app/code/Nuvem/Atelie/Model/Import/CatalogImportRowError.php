<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Import;

use Nuvem\Atelie\Api\Data\CatalogImportRowErrorInterface;

/**
 * Class CatalogImportRowError
 *
 * Value object holding row error details.
 */
class CatalogImportRowError implements CatalogImportRowErrorInterface
{
    /**
     * @param int $rowNumber
     * @param string|null $componentCode
     * @param string $reason
     */
    public function __construct(
        private readonly int $rowNumber,
        private readonly ?string $componentCode,
        private readonly string $reason
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    /**
     * @inheritdoc
     */
    public function getComponentCode(): ?string
    {
        return $this->componentCode;
    }

    /**
     * @inheritdoc
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
