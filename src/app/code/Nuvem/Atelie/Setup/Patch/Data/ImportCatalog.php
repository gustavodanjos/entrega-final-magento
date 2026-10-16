<?php

declare(strict_types=1);

namespace Nuvem\Atelie\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use RuntimeException;
use Throwable;

class ImportCatalog implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup
    ) {
    }

    public function apply(): self
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();

        $connection = $setup->getConnection();
        $connection->beginTransaction();

        try {
            $groupTable = $setup->getTable('nuvem_atelie_grupo');
            $componentTable = $setup->getTable('nuvem_atelie_componente');
            $ruleTable = $setup->getTable('nuvem_atelie_regra');

            $this->importGroups($groupTable);
            $groupIds = $this->getIdsByCode($groupTable, 'grupo_id');

            $this->importComponents($componentTable, $groupIds);
            $componentIds = $this->getIdsByCode($componentTable, 'componente_id');

            $this->importRules($ruleTable, $componentIds);

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        } finally {
            $setup->endSetup();
        }

        return $this;
    }

    private function importGroups(string $table): void
    {
        $rows = $this->readCsv('grupos.csv', [
            'codigo',
            'nome',
            'ordem',
            'camada_svg',
        ]);

        foreach ($rows as $row) {
            $this->moduleDataSetup->getConnection()->insertOnDuplicate(
                $table,
                [
                    'codigo' => $row['codigo'],
                    'nome' => $row['nome'],
                    'ordem' => (int) $row['ordem'],
                    'camada_svg' => $row['camada_svg'],
                    'ativo' => 1,
                ],
                ['nome', 'ordem', 'camada_svg', 'ativo']
            );
        }
    }

    /**
     * @param array<string, int> $groupIds
     */
    private function importComponents(string $table, array $groupIds): void
    {
        $rows = $this->readCsv('componentes.csv', [
            'codigo',
            'grupo',
            'nome',
            'cor_hex',
            'acabamento',
            'preco_adicional',
            'estoque',
            'padrao',
        ]);

        $connection = $this->moduleDataSetup->getConnection();
        $orders = [];

        foreach ($rows as $row) {
            $groupCode = $row['grupo'];

            if (!isset($groupIds[$groupCode])) {
                throw new RuntimeException(
                    sprintf('O grupo "%s" do componente "%s" não existe.', $groupCode, $row['codigo'])
                );
            }

            $orders[$groupCode] = ($orders[$groupCode] ?? 0) + 10;

            $connection->insertOnDuplicate(
                $table,
                [
                    'codigo' => $row['codigo'],
                    'grupo_id' => $groupIds[$groupCode],
                    'nome' => $row['nome'],
                    'cor_hex' => $row['cor_hex'],
                    'acabamento' => $row['acabamento'],
                    'preco_adicional' => $row['preco_adicional'],
                    'estoque' => (int) $row['estoque'],
                    'padrao' => $this->toBoolean($row['padrao']),
                    'ativo' => 1,
                    'ordem' => $orders[$groupCode],
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                [
                    'grupo_id',
                    'nome',
                    'cor_hex',
                    'acabamento',
                    'preco_adicional',
                    'padrao',
                    'ativo',
                    'ordem',
                ]
            );
        }
    }

    /**
     * @param array<string, int> $componentIds
     */
    private function importRules(string $table, array $componentIds): void
    {
        $rows = $this->readCsv('regras.csv', [
            'codigo',
            'tipo',
            'origem',
            'alvo_tipo',
            'alvo',
            'mensagem',
        ]);

        $connection = $this->moduleDataSetup->getConnection();

        foreach ($rows as $row) {
            $originCode = $row['origem'];

            if (!isset($componentIds[$originCode])) {
                throw new RuntimeException(
                    sprintf('O componente de origem "%s" da regra "%s" não existe.', $originCode, $row['codigo'])
                );
            }

            if ($row['alvo_tipo'] === 'componente' && !isset($componentIds[$row['alvo']])) {
                throw new RuntimeException(
                    sprintf('O componente de destino "%s" da regra "%s" não existe.', $row['alvo'], $row['codigo'])
                );
            }

            $connection->insertOnDuplicate(
                $table,
                [
                    'codigo' => $row['codigo'],
                    'tipo' => $row['tipo'],
                    'origem_componente_id' => $componentIds[$originCode],
                    'alvo_tipo' => $row['alvo_tipo'],
                    'alvo' => $row['alvo'],
                    'mensagem' => $row['mensagem'],
                    'ativo' => 1,
                ],
                [
                    'tipo',
                    'origem_componente_id',
                    'alvo_tipo',
                    'alvo',
                    'mensagem',
                    'ativo',
                ]
            );
        }
    }

    /**
     * @return array<string, int>
     */
    private function getIdsByCode(string $table, string $idColumn): array
    {
        $rows = $this->moduleDataSetup->getConnection()->fetchAll(
            $this->moduleDataSetup->getConnection()->select()
                ->from($table, [$idColumn, 'codigo'])
        );

        $ids = [];

        foreach ($rows as $row) {
            $ids[$row['codigo']] = (int) $row[$idColumn];
        }

        return $ids;
    }

    /**
     * @param string[] $requiredHeaders
     * @return array<int, array<string, string>>
     */
    private function readCsv(string $filename, array $requiredHeaders): array
    {
        $path = dirname(__DIR__, 2) . '/fixtures/' . $filename;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(sprintf('Não foi possível abrir o arquivo %s.', $path));
        }

        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');

            if ($headers === false) {
                throw new RuntimeException(sprintf('O arquivo %s está vazio.', $filename));
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $headers = array_map(static fn ($header): string => trim((string) $header), $headers);

            foreach ($requiredHeaders as $header) {
                if (!in_array($header, $headers, true)) {
                    throw new RuntimeException(
                        sprintf('A coluna obrigatória "%s" não existe em %s.', $header, $filename)
                    );
                }
            }

            $rows = [];

            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }

                if (count($values) !== count($headers)) {
                    throw new RuntimeException(
                        sprintf('Há uma linha com número incorreto de colunas em %s.', $filename)
                    );
                }

                $row = array_combine($headers, $values);

                if ($row !== false) {
                    $rows[] = array_map(
                        static fn ($value): string => trim((string) $value),
                        $row
                    );
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function toBoolean(string $value): int
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'sim', 'yes'], true) ? 1 : 0;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}