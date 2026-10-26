<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api;

use Nuvem\Atelie\Api\Data\CatalogImportResultInterface;

/**
 * Interface CatalogImporterInterface
 *
 * Service contract for importing customization components into the catalog.
 * Validates all rows prior to persistence, runs write operations inside a database
 * transaction, and returns a detailed execution summary.
 *
 * @api
 */
interface CatalogImporterInterface
{
    /**
     * Import components from a spreadsheet file (CSV)
     *
     * @param string $filePath Absolute or resolved path to CSV file
     * @param bool $simulate If true, validates and calculates summary without persisting
     * @return CatalogImportResultInterface
     */
    public function import(string $filePath, bool $simulate = false): CatalogImportResultInterface;
}
