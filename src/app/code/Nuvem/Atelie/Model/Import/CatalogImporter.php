<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Import;

use InvalidArgumentException;
use Magento\Framework\App\ResourceConnection;
use Nuvem\Atelie\Api\CatalogImporterInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\CatalogImportResultInterface;
use Nuvem\Atelie\Model\ComponentFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

/**
 * Class CatalogImporter
 *
 * Implements CatalogImporterInterface to import components from a CSV spreadsheet.
 */
class CatalogImporter implements CatalogImporterInterface
{
    /**
     * @param CsvReader $csvReader
     * @param ComponentRowValidator $rowValidator
     * @param ComponentRepositoryInterface $componentRepository
     * @param ComponentFactory $componentFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CsvReader $csvReader,
        private readonly ComponentRowValidator $rowValidator,
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly ComponentFactory $componentFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function import(string $filePath, bool $simulate = false): CatalogImportResultInterface
    {
        try {
            $rawRows = $this->csvReader->read($filePath);
        } catch (RuntimeException | InvalidArgumentException $exception) {
            return new CatalogImportResult(
                0,
                0,
                1,
                $simulate,
                false,
                [new CatalogImportRowError(1, null, $exception->getMessage())]
            );
        }

        if (empty($rawRows)) {
            return new CatalogImportResult(
                0,
                0,
                1,
                $simulate,
                false,
                [new CatalogImportRowError(1, null, 'O arquivo informado não contém linhas de dados para importar.')]
            );
        }

        $availableGroups = $this->loadAvailableGroups();

        $allErrors = [];
        $validatedRows = [];
        $seenCodes = [];

        foreach ($rawRows as $rawRow) {
            $validation = $this->rowValidator->validate($rawRow, $availableGroups, $seenCodes);

            if (!empty($validation['errors'])) {
                foreach ($validation['errors'] as $error) {
                    $allErrors[] = $error;
                }
            } else {
                $code = $validation['data']['codigo'];
                $seenCodes[$code] = (int) ($rawRow['_line_number'] ?? 0);
                $validatedRows[] = $validation['data'];
            }
        }

        // If any error was found, reject the entire file without writing anything
        if (!empty($allErrors)) {
            return new CatalogImportResult(
                0,
                0,
                count($allErrors),
                $simulate,
                false,
                $allErrors
            );
        }

        $codes = array_column($validatedRows, 'codigo');
        $existingMap = $this->loadExistingComponentCodes($codes);

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($validatedRows as $row) {
            if (isset($existingMap[$row['codigo']])) {
                $updatedCount++;
            } else {
                $createdCount++;
            }
        }

        // In simulation mode, return summary without persisting
        if ($simulate) {
            return new CatalogImportResult(
                $createdCount,
                $updatedCount,
                0,
                true,
                true,
                [],
                ['Simulação concluída com sucesso. Nenhuma alteração foi persistida no banco de dados.']
            );
        }

        // Persist inside a database transaction
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $groupOrderCounters = [];

            foreach ($validatedRows as $data) {
                $code = (string) $data['codigo'];
                $isUpdate = isset($existingMap[$code]);

                if ($isUpdate) {
                    $component = $this->componentRepository->getByCodigo($code);
                } else {
                    $component = $this->componentFactory->create();
                    $component->setCodigo($code);
                }

                $component->setGrupoId((int) $data['grupo_id']);
                $component->setNome((string) $data['nome']);
                $component->setCorHex((string) $data['cor_hex']);
                $component->setAcabamento((string) $data['acabamento']);
                $component->setPrecoAdicional((float) $data['preco_adicional']);
                $component->setEstoque((int) $data['estoque']);
                $component->setPadrao((int) $data['padrao']);
                $component->setAtivo((int) $data['ativo']);

                if ($data['ordem'] !== null) {
                    $component->setOrdem((int) $data['ordem']);
                } elseif (!$isUpdate) {
                    $groupId = (int) $data['grupo_id'];
                    $groupOrderCounters[$groupId] = ($groupOrderCounters[$groupId] ?? 0) + 10;
                    $component->setOrdem($groupOrderCounters[$groupId]);
                }

                $this->componentRepository->save($component);
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            $this->logger->error('Atelie: Catalog import transaction failed.', [
                'file' => $filePath,
                'exception' => $exception
            ]);

            return new CatalogImportResult(
                0,
                0,
                1,
                false,
                false,
                [new CatalogImportRowError(0, null, 'Erro na transação ao salvar dados: ' . $exception->getMessage())]
            );
        }

        return new CatalogImportResult(
            $createdCount,
            $updatedCount,
            0,
            false,
            true,
            [],
            ['Importação executada e gravada com sucesso!']
        );
    }

    /**
     * Load map of group code/name/id to grupo_id
     *
     * @return array<string, int>
     */
    private function loadAvailableGroups(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('nuvem_atelie_grupo');

        $select = $connection->select()
            ->from($tableName, ['grupo_id', 'codigo', 'nome']);

        $rows = $connection->fetchAll($select);
        $groups = [];

        foreach ($rows as $row) {
            $id = (int) $row['grupo_id'];
            $code = mb_strtolower(trim((string) $row['codigo']), 'UTF-8');
            $name = mb_strtolower(trim((string) $row['nome']), 'UTF-8');

            $groups[$code] = $id;
            $groups[$name] = $id;
            $groups[(string) $id] = $id;
        }

        return $groups;
    }

    /**
     * Load existing component codes from database
     *
     * @param string[] $codes
     * @return array<string, int> Map of component code to componente_id
     */
    private function loadExistingComponentCodes(array $codes): array
    {
        if (empty($codes)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('nuvem_atelie_componente');

        $select = $connection->select()
            ->from($tableName, ['codigo', 'componente_id'])
            ->where('codigo IN (?)', $codes);

        $pairs = $connection->fetchPairs($select);
        $map = [];

        foreach ($pairs as $code => $id) {
            $map[(string) $code] = (int) $id;
        }

        return $map;
    }
}
