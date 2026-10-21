<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Movimentacao
 *
 * Resource model of the stock ledger. The ledger is append-only: rows are
 * inserted by the stock service and never updated or deleted.
 */
class Movimentacao extends AbstractDb
{
    public const TIPO_RESERVA = 'reserva';

    public const TIPO_COMPENSACAO = 'compensacao';

    public const TIPO_DEVOLUCAO = 'devolucao';

    public const TIPO_AJUSTE = 'ajuste';

    private const TABLE_COMPONENTE = 'nuvem_atelie_componente';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('nuvem_atelie_movimentacao', 'movimentacao_id');
    }

    /**
     * Net quantity still owed per component for a quote.
     *
     * Every ledger row attributed to the quote counts towards the balance:
     * a negative net means the quote still owes stock to the ledger, a zero
     * net means the quote is already balanced. This makes the compensation
     * idempotent: a second compensation attempt finds net zero and does nothing.
     *
     * @param int $quoteId
     * @return array<int, array{componente_id: int, codigo: string, net: int}>
     */
    public function getNetQuantitiesByQuoteId(int $quoteId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                ['movimentacao' => $this->getMainTable()],
                [
                    'componente_id' => 'movimentacao.componente_id',
                    'net' => 'SUM(movimentacao.quantidade)',
                ]
            )
            ->join(
                ['componente' => $this->getTable(self::TABLE_COMPONENTE)],
                'componente.componente_id = movimentacao.componente_id',
                ['codigo' => 'componente.codigo']
            )
            ->where('movimentacao.quote_id = ?', $quoteId)
            ->group('movimentacao.componente_id');

        return array_map(
            static fn (array $row): array => [
                'componente_id' => (int) $row['componente_id'],
                'codigo' => (string) $row['codigo'],
                'net' => (int) $row['net'],
            ],
            $connection->fetchAll($select)
        );
    }

    /**
     * Quantity of each component already returned for a specific order item.
     *
     * @param int $orderItemId
     * @return array<string, int> component code => total quantity returned
     */
    public function getReturnedQuantitiesByOrderItemId(int $orderItemId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                ['movimentacao' => $this->getMainTable()],
                [
                    'codigo' => 'componente.codigo',
                    'total' => 'SUM(movimentacao.quantidade)',
                ]
            )
            ->join(
                ['componente' => $this->getTable(self::TABLE_COMPONENTE)],
                'componente.componente_id = movimentacao.componente_id',
                []
            )
            ->where('movimentacao.order_item_id = ?', $orderItemId)
            ->where('movimentacao.tipo = ?', self::TIPO_DEVOLUCAO)
            ->group('componente.codigo');

        $rows = $connection->fetchPairs($select);
        $result = [];
        foreach ($rows as $code => $total) {
            $result[(string) $code] = (int) $total;
        }

        return $result;
    }
}

