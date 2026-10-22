<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\ResourceModel;

use Magento\Framework\Exception\LocalizedException;
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
     * Serialize stock operations belonging to the same persisted quote.
     *
     * @param int $quoteId
     * @return void
     * @throws LocalizedException
     */
    public function lockQuote(int $quoteId): void
    {
        $connection = $this->getConnection();
        $quoteId = $connection->fetchOne(
            $connection->select()->from($this->getTable('quote'), 'entity_id')
                ->where('entity_id = ?', $quoteId)->forUpdate(true)
        );
        if (!$quoteId) {
            throw new LocalizedException(__('O carrinho não existe mais. Atualize a página e tente novamente.'));
        }
    }

    /**
     * Current read avoids an enclosing transaction's repeatable-read snapshot.
     * The caller must hold the quote lock until its movement is committed.
     *
     * @param int $quoteId
     * @param int $componentId
     * @return int
     */
    public function getReservedQuantity(int $quoteId, int $componentId): int
    {
        $quantities = $this->getConnection()->fetchCol(
            $this->getConnection()->select()->from($this->getMainTable(), 'quantidade')
                ->where('quote_id = ?', $quoteId)->where('componente_id = ?', $componentId)
                ->forUpdate(true)
        );
        return max(0, -(int) array_sum($quantities));
    }

    /**
     * Net quantity still owed per component for a quote.
     *
     * Every ledger row attributed to the quote counts towards the balance:
     * a negative net means the quote still owes stock to the ledger, a zero
     * net means the quote is already balanced. This makes the compensation
     * idempotent when the caller locks the quote before reading the balance.
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
                    'quantidade' => 'movimentacao.quantidade',
                ]
            )
            ->join(
                ['componente' => $this->getTable(self::TABLE_COMPONENTE)],
                'componente.componente_id = movimentacao.componente_id',
                ['codigo' => 'componente.codigo']
            )
            ->where('movimentacao.quote_id = ?', $quoteId)
            ->forUpdate(true);

        // A locking read sees committed movements even when an outer transaction has an older snapshot.
        $balances = [];
        foreach ($connection->fetchAll($select) as $row) {
            $componentId = (int) $row['componente_id'];
            if (!isset($balances[$componentId])) {
                $balances[$componentId] = [
                    'componente_id' => $componentId,
                    'codigo' => (string) $row['codigo'],
                    'net' => 0,
                ];
            }
            $balances[$componentId]['net'] += (int) $row['quantidade'];
        }
        return array_values($balances);
    }
}
