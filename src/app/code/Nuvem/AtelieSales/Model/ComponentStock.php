<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Store\Model\ScopeInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Model\Config;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Api\ReturnComponentStockInterface;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;
use Zend_Db_Expr;

/**
 * Class ComponentStock
 *
 * Real stock service. The stock column is unsigned and only ever changes
 * through guarded SQL, so two orders racing for the last unit can never both
 * win: the UPDATE below only succeeds when the units are really there.
 *
 * Every movement is a ledger row in the same database transaction as the
 * stock change, so the ledger always explains the stock. When a component
 * sells out its cache identities are invalidated for the storefront.
 */
class ComponentStock implements ComponentStockInterface, QuoteComponentStockInterface, ReturnComponentStockInterface
{
    private const TABLE_COMPONENTE = 'nuvem_atelie_componente';

    /** @var array<int, \WeakReference<\stdClass>> */
    private array $availabilityChanges = [];

    /**
     * @var ComponentRepositoryInterface
     */
    private ComponentRepositoryInterface $componentRepository;

    /**
     * @var Movimentacao
     */
    private Movimentacao $movimentacaoResource;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var AtelieCatalogInterface
     */
    private AtelieCatalogInterface $atelieCatalog;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var ManagerInterface
     */
    private ManagerInterface $eventManager;

    /**
     * @param ComponentRepositoryInterface $componentRepository
     * @param Movimentacao $movimentacaoResource
     * @param ResourceConnection $resourceConnection
     * @param AtelieCatalogInterface $atelieCatalog
     * @param ScopeConfigInterface $scopeConfig
     * @param ManagerInterface $eventManager
     */
    public function __construct(
        ComponentRepositoryInterface $componentRepository,
        Movimentacao $movimentacaoResource,
        ResourceConnection $resourceConnection,
        AtelieCatalogInterface $atelieCatalog,
        ScopeConfigInterface $scopeConfig,
        ManagerInterface $eventManager
    ) {
        $this->componentRepository = $componentRepository;
        $this->movimentacaoResource = $movimentacaoResource;
        $this->resourceConnection = $resourceConnection;
        $this->atelieCatalog = $atelieCatalog;
        $this->scopeConfig = $scopeConfig;
        $this->eventManager = $eventManager;
    }

    /**
     * @inheritDoc
     */
    public function reserve(string $componentCode, int $qty, ?int $orderId = null, ?int $orderItemId = null): bool
    {
        return $this->reserveStock($componentCode, $qty, $orderId, $orderItemId, null);
    }

    /**
     * @inheritDoc
     */
    public function reserveForQuote(string $componentCode, int $qty, int $quoteId): bool
    {
        return $this->reserveStock($componentCode, $qty, null, null, $quoteId);
    }

    /**
     * Reserve stock for a quote.
     *
     * @param string $componentCode
     * @param int $qty
     * @param int|null $orderId
     * @param int|null $orderItemId
     * @param int|null $quoteId
     * @return bool
     */
    private function reserveStock(
        string $componentCode,
        int $qty,
        ?int $orderId,
        ?int $orderItemId,
        ?int $quoteId
    ): bool {
        $this->assertPositiveQuantity($qty);
        $component = $this->resolveComponent($componentCode);
        $connection = $this->connection();

        $connection->beginTransaction();
        try {
            if ($quoteId !== null) {
                $this->movimentacaoResource->lockQuote($quoteId);
            }
            $currentQty = $this->getCurrentStockForUpdate($component);
            $affectedRows = $connection->update(
                $this->resourceConnection->getTableName(self::TABLE_COMPONENTE),
                ['estoque' => new Zend_Db_Expr('estoque - ' . $qty)],
                [
                    'componente_id = ?' => (int) $component->getComponenteId(),
                    'estoque >= ?' => $qty,
                ]
            );

            if ($affectedRows !== 1) {
                throw new LocalizedException(
                    __(
                        'O componente "%1" esgotou enquanto você finalizava. Troque e tente de novo.',
                        $component->getNome() ?: $componentCode
                    )
                );
            }

            $this->recordMovement(
                $component,
                Movimentacao::TIPO_RESERVA,
                -$qty,
                $quoteId,
                $orderId,
                $orderItemId,
                null
            );
            $this->scheduleCacheInvalidationIfAvailabilityChanges($component, $currentQty, $currentQty - $qty);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function compensate(string $componentCode, int $qty, ?int $orderId = null): void
    {
        $this->compensateStock($componentCode, $qty, $orderId, null);
    }

    /**
     * @inheritDoc
     */
    public function compensateForQuote(string $componentCode, int $qty, int $quoteId): void
    {
        $this->compensateStock($componentCode, $qty, null, $quoteId);
    }

    /**
     * Compensate a quote reservation.
     *
     * @param string $componentCode
     * @param int $qty
     * @param int|null $orderId
     * @param int|null $quoteId
     * @return void
     */
    private function compensateStock(string $componentCode, int $qty, ?int $orderId, ?int $quoteId): void
    {
        $this->assertPositiveQuantity($qty);
        $component = $this->resolveComponent($componentCode);
        $connection = $this->connection();

        $connection->beginTransaction();
        try {
            if ($quoteId !== null) {
                $this->movimentacaoResource->lockQuote($quoteId);
                $qty = min($qty, $this->movimentacaoResource->getReservedQuantity(
                    $quoteId,
                    (int) $component->getComponenteId()
                ));
            }
            if ($qty === 0) {
                $connection->commit();
                return;
            }
            $currentQty = $this->getCurrentStockForUpdate($component);
            $this->increaseStock($component, $qty);
            $this->recordMovement($component, Movimentacao::TIPO_COMPENSACAO, $qty, $quoteId, $orderId, null, null);
            $this->scheduleCacheInvalidationIfAvailabilityChanges($component, $currentQty, $currentQty + $qty);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function return(
        string $componentCode,
        int $qty,
        ?int $orderId = null,
        ?int $orderItemId = null,
        ?string $note = null
    ): void
    {
        $this->assertPositiveQuantity($qty);
        $component = $this->resolveComponent($componentCode);
        $connection = $this->connection();

        $connection->beginTransaction();
        try {
            $currentQty = $this->getCurrentStockForUpdate($component);
            $this->increaseStock($component, $qty);
            $this->recordMovement(
                $component,
                Movimentacao::TIPO_DEVOLUCAO,
                $qty,
                null,
                $orderId,
                $orderItemId,
                $note
            );
            $this->scheduleCacheInvalidationIfAvailabilityChanges($component, $currentQty, $currentQty + $qty);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function adjust(string $componentCode, int $newQty, string $note = ''): void
    {
        if ($newQty < 0) {
            throw new LocalizedException(__('A quantidade em estoque não pode ser negativa.'));
        }

        $component = $this->resolveComponent($componentCode);
        $connection = $this->connection();

        $connection->beginTransaction();
        try {
            $currentQty = $this->getCurrentStockForUpdate($component);
            $connection->update(
                $this->resourceConnection->getTableName(self::TABLE_COMPONENTE),
                ['estoque' => $newQty],
                ['componente_id = ?' => (int) $component->getComponenteId()]
            );

            if ($newQty !== $currentQty) {
                $this->recordMovement(
                    $component,
                    Movimentacao::TIPO_AJUSTE,
                    $newQty - $currentQty,
                    null,
                    null,
                    null,
                    $note !== '' ? $note : null
                );
            }
            $this->scheduleCacheInvalidationIfAvailabilityChanges($component, $currentQty, $newQty);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @inheritDoc
     */
    public function availability(string $sku): array
    {
        $catalog = $this->atelieCatalog->getBySku($sku);

        $componentCodes = [];
        foreach ($catalog['grupos'] ?? [] as $group) {
            foreach ($group['componentes'] ?? [] as $componentData) {
                if (!empty($componentData['codigo'])) {
                    $componentCodes[] = (string) $componentData['codigo'];
                }
            }
        }

        if ($componentCodes === []) {
            return [];
        }

        $stockByCode = $this->loadStockByCode($componentCodes);
        $lastUnitsLimit = (int) $this->scopeConfig->getValue(
            Config::XML_PATH_STOCK_LAST_UNITS,
            ScopeInterface::SCOPE_STORE
        );

        $availability = [];
        foreach ($componentCodes as $componentCode) {
            $stock = $stockByCode[$componentCode] ?? 0;
            $availability[] = [
                'component' => $componentCode,
                'available' => $stock > 0,
                'last_units' => $stock > 0 && $stock <= $lastUnitsLimit,
            ];
        }

        return $availability;
    }

    /**
     * Increment the stock of a component. Only used for movements that give units back.
     *
     * @param ComponentInterface $component
     * @param int $qty
     * @return void
     */
    private function increaseStock(ComponentInterface $component, int $qty): void
    {
        $this->connection()->update(
            $this->resourceConnection->getTableName(self::TABLE_COMPONENTE),
            ['estoque' => new Zend_Db_Expr('estoque + ' . $qty)],
            ['componente_id = ?' => (int) $component->getComponenteId()]
        );
    }

    /**
     * Read stock under the row lock; the guarded reservation UPDATE remains authoritative.
     *
     * @param ComponentInterface $component
     * @return int
     */
    private function getCurrentStockForUpdate(ComponentInterface $component): int
    {
        return (int) $this->connection()->fetchOne(
            $this->connection()->select()
                ->from($this->resourceConnection->getTableName(self::TABLE_COMPONENTE), 'estoque')
                ->where('componente_id = ?', (int) $component->getComponenteId())
                ->forUpdate(true)
        );
    }

    /**
     * Append a ledger row describing one stock movement.
     *
     * @param ComponentInterface $component
     * @param string $tipo one of the Movimentacao::TIPO_* constants
     * @param int $qty signed quantity: negative leaves stock, positive returns to it
     * @param int|null $quoteId
     * @param int|null $orderId
     * @param int|null $orderItemId
     * @param string|null $note
     * @return void
     */
    private function recordMovement(
        ComponentInterface $component,
        string $tipo,
        int $qty,
        ?int $quoteId,
        ?int $orderId,
        ?int $orderItemId,
        ?string $note
    ): void {
        $this->connection()->insert(
            $this->movimentacaoResource->getMainTable(),
            [
                'componente_id' => (int) $component->getComponenteId(),
                'tipo' => $tipo,
                'quantidade' => $qty,
                'quote_id' => $quoteId,
                'order_id' => $orderId,
                'order_item_id' => $orderItemId,
                'observacao' => $note,
            ]
        );
    }

    /**
     * Load the current stock of the given component codes, indexed by code.
     *
     * @param string[] $componentCodes
     * @return array<string, int>
     */
    private function loadStockByCode(array $componentCodes): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->resourceConnection->getTableName(self::TABLE_COMPONENTE), ['codigo', 'estoque'])
            ->where('codigo IN (?)', $componentCodes);

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Cache readers must see committed stock, including replenishments.
     *
     * Magento discards these callbacks when an enclosing transaction rolls back.
     *
     * @param ComponentInterface $component
     * @param int $beforeQty
     * @param int $afterQty
     * @return void
     */
    private function scheduleCacheInvalidationIfAvailabilityChanges(
        ComponentInterface $component,
        int $beforeQty,
        int $afterQty
    ): void {
        $lastUnitsLimit = (int) $this->scopeConfig->getValue(Config::XML_PATH_STOCK_LAST_UNITS);
        $before = [$beforeQty > 0, $beforeQty > 0 && $beforeQty <= $lastUnitsLimit];
        $after = [$afterQty > 0, $afterQty > 0 && $afterQty <= $lastUnitsLimit];
        if ($before === $after) {
            return;
        }
        $componentId = (int) $component->getComponenteId();
        $change = ($this->availabilityChanges[$componentId] ?? null)?->get();
        if ($change !== null) {
            $change->after = $after;
            return;
        }
        $change = (object) ['before' => $before, 'after' => $after];
        // The callback pool owns this snapshot; a rollback discards it with its callback.
        $this->availabilityChanges[$componentId] = \WeakReference::create($change);
        $this->movimentacaoResource->addCommitCallback(function () use ($component, $componentId, $change): void {
            unset($this->availabilityChanges[$componentId]);
            if ($change->before !== $change->after) {
                $this->invalidateComponentCache($component);
            }
        });
    }

    /**
     * Clean the component cache identities: built-in cache plus full page cache.
     *
     * Mirrors what a model save does (AbstractModel::afterSave), because the
     * stock is changed through guarded SQL instead of the model.
     *
     * @param ComponentInterface $component
     * @return void
     */
    private function invalidateComponentCache(ComponentInterface $component): void
    {
        if ($component instanceof AbstractModel) {
            $component->cleanModelCache();
        }
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $component]);
    }

    /**
     * Resolve a component by its code.
     *
     * @param string $componentCode
     * @return ComponentInterface
     * @throws LocalizedException when the component does not exist
     */
    private function resolveComponent(string $componentCode): ComponentInterface
    {
        try {
            return $this->componentRepository->getByCodigo($componentCode);
        } catch (NoSuchEntityException $exception) {
            throw new LocalizedException(__('O componente "%1" não existe.', $componentCode));
        }
    }

    /**
     * Require a positive stock movement quantity.
     *
     * @param int $qty
     * @return void
     * @throws LocalizedException when the quantity is not positive
     */
    private function assertPositiveQuantity(int $qty): void
    {
        if ($qty <= 0) {
            throw new LocalizedException(__('A quantidade deve ser maior que zero.'));
        }
    }

    /**
     * Return the active database connection.
     *
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resourceConnection->getConnection();
    }
}
