<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ObjectManager;
use Magento\PageCache\Model\Cache\Type as FullPageCache;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Event\Config\Data as EventConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\ComponentFactory;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Api\QuoteComponentStockInterface;
use Nuvem\AtelieSales\Model\ComponentStock;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;

require dirname(__DIR__, 6) . '/app/bootstrap.php';
require __DIR__ . '/CheckoutFixture.php';
require __DIR__ . '/ConcurrentStockScenarios.php';

final class FailingStockLedger extends Movimentacao
{
    public function getMainTable(): string
    {
        return $this->getTable('f23_deliberately_missing_ledger');
    }
}

final class FailingSubmissionObserver implements ObserverInterface
{
    public static array $codes = [];
    public static array $atFailure = [];
    public static ?string $stateAtFailure = null;

    public function execute(Observer $observer): void
    {
        $manager = ObjectManager::getInstance();
        self::$atFailure = (new CheckoutFixture($manager))->stocks(self::$codes);
        $quoteId = (int) $observer->getEvent()->getOrder()->getQuoteId();
        self::$stateAtFailure = $manager->get(Movimentacao::class)->getAttempt($quoteId)['state'];
        throw new LocalizedException(__('F2.3 integration: forced submission failure.'));
    }
}

function assertStockScenario(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$resource = $objectManager->get(ResourceConnection::class);
$connection = $resource->getConnection();
$database = (string) $connection->fetchOne('SELECT DATABASE()');
assertStockScenario(
    str_starts_with($database, 'f23_validation_'),
    'Run exclusively against an isolated database named f23_validation_*.'
);
$objectManager->get(State::class)->setAreaCode('frontend');
$stock = $objectManager->get(ComponentStockInterface::class);
$quoteStock = $objectManager->get(QuoteComponentStockInterface::class);
$repository = $objectManager->get(ComponentRepositoryInterface::class);
$ledger = $objectManager->get(Movimentacao::class);
$command = $argv[1] ?? 'transactions';
$fixture = new CheckoutFixture($objectManager);
ConcurrentStockScenarios::waitForStart($argv);

if (in_array($command, [
    'reentry', 'recovery', 'order-snapshot', 'active-owner', 'duplicate-reservation', 'stale-failure', 'stale-order',
    'legacy-balance', 'partial-reservation',
], true)) {
    if (in_array($command, ['stale-failure', 'stale-order', 'legacy-balance', 'partial-reservation'], true)) {
        require __DIR__ . '/OwnershipScenarios.php';
    } else {
        require __DIR__ . '/AttemptScenarios.php';
    }
} elseif (in_array($command, [
    'reset-fixture', 'prepare', 'submit', 'race', 'duplicate', 'concurrent-compensation', 'compensate-quote',
], true)) {
    require __DIR__ . '/CheckoutScenarios.php';
} elseif ($command === 'transactions') {
    $template = $repository->getByCodigo('SOL-LUA-HOLO');
    $component = $objectManager->get(ComponentFactory::class)->create();
    $component->setData($template->getData());
    $component->setId(null)->setCodigo('F23-TRANSACTION-TEST')->setEstoque(10)->setAtivo(false);
    $repository->save($component);
    $code = $component->getCodigo();
    $quoteId = (int) $objectManager->get(CartManagementInterface::class)->createEmptyCart();
    $cache = $objectManager->get(FullPageCache::class);
    $tag = Component::STOREFRONT_CACHE_TAG_PREFIX . $code;
    $cacheKey = 'f23_transaction_probe';
    try {
        $cache->save('cached', $cacheKey, [$tag]);
        $connection->beginTransaction();
        $quoteStock->reserveForQuote($code, 10, $quoteId);
        assertStockScenario($cache->load($cacheKey) === 'cached', 'Cache invalidated before outer commit.');
        $connection->rollBack();
        assertStockScenario($cache->load($cacheKey) === 'cached', 'Rollback did not discard cache callback.');
        assertStockScenario($repository->getByCodigo($code)->getEstoque() === 10, 'Nested rollback lost stock.');
        assertStockScenario($ledger->getNetQuantitiesByQuoteId($quoteId) === [], 'Rollback left ledger rows.');

        $connection->beginTransaction();
        $quoteStock->reserveForQuote($code, 10, $quoteId);
        $connection->commit();
        assertStockScenario($cache->load($cacheKey) === false, 'Outer commit did not invalidate FPC.');
        $cache->save('cached', $cacheKey, [$tag]);
        $quoteStock->compensateForQuote($code, 50, $quoteId);
        $quoteStock->compensateForQuote($code, 50, $quoteId);
        assertStockScenario($repository->getByCodigo($code)->getEstoque() === 10, 'Compensation exceeded reservation.');
        assertStockScenario($ledger->getNetQuantitiesByQuoteId($quoteId)[0]['net'] === 0, 'Compensation ledger unbalanced.');
        assertStockScenario($cache->load($cacheKey) === false, 'Replenishment did not invalidate FPC.');

        $brokenStock = $objectManager->create(ComponentStock::class, [
            'movimentacaoResource' => $objectManager->create(FailingStockLedger::class),
        ]);
        $connection->beginTransaction();
        try {
            $brokenStock->reserveForQuote($code, 1, $quoteId);
            throw new LogicException('Expected SQL ledger failure.');
        } catch (LogicException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $connection->rollBack();
        }
        assertStockScenario($connection->getTransactionLevel() === 0, 'SQL failure leaked nested transaction.');
        assertStockScenario($repository->getByCodigo($code)->getEstoque() === 10, 'SQL failure lost stock.');
        echo "PASS: nested rollback, SQL failure, commit callbacks, FPC and capped idempotent compensation\n";
    } finally {
        while ($connection->getTransactionLevel() > 0) {
            $connection->rollBack();
        }
        $cache->remove($cacheKey);
        $repository->delete($component);
        $quotes = $objectManager->get(CartRepositoryInterface::class);
        $quotes->delete($quotes->get($quoteId));
    }
} elseif ($command === 'failure') {
    $quoteId = (int) ($argv[2] ?? 0);
    $event = $argv[3] ?? 'sales_order_place_before';
    assertStockScenario(in_array($event, [
        'sales_order_place_before', 'sales_order_save_after', 'sales_model_service_quote_submit_before',
        'sales_model_service_quote_submit_success',
    ], true), 'Unsupported failure event.');
    $quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteId);
    $quote->setCustomerIsGuest(true)->setCustomerEmail('f23-integration@example.test');
    $quote->getPayment()->setMethod('checkmo');
    $codes = [];
    $configurationReader = $objectManager->get(QuoteItemConfigurationReader::class);
    foreach ($quote->getAllVisibleItems() as $item) {
        $codes = array_merge($codes, array_keys($configurationReader->getComponentQuantities($item)));
    }
    assertStockScenario(count(array_unique($codes)) === 7, 'Expected seven persisted components.');
    FailingSubmissionObserver::$codes = $codes;
    $before = $fixture->stocks($codes);
    $events = $objectManager->get(EventConfig::class);
    $events->get($event);
    $events->merge([$event => ['f23_integration_failure' => [
        'name' => 'f23_integration_failure', 'instance' => FailingSubmissionObserver::class,
    ]]]);
    try {
        $objectManager->get(QuoteManagement::class)->submit($quote);
        throw new LogicException('Expected checkout failure.');
    } catch (LogicException $exception) {
        throw $exception;
    } catch (LocalizedException $exception) {
        assertStockScenario(str_contains($exception->getMessage(), 'forced submission failure'), $exception->getMessage());
    }
    $net = array_column($ledger->getNetQuantitiesByQuoteId($quoteId), 'net');
    $persisted = $event === 'sales_model_service_quote_submit_success';
    $completedAtFailure = in_array($event, ['sales_order_save_after', 'sales_model_service_quote_submit_success'], true);
    assertStockScenario(FailingSubmissionObserver::$stateAtFailure === ($completedAtFailure ? 'completed' : 'active'),
        'Order completion must participate in the order save transaction.');
    assertStockScenario(count($net) === 7 && count(array_unique($net)) === 1, 'Expected all seven ledger balances.');
    assertStockScenario($net[0] === ($persisted ? -1 : 0), 'Unexpected checkout compensation balance.');
    $after = $fixture->stocks($codes);
    $movements = $fixture->movements([$quoteId]);
    $orderCount = (int) $connection->fetchOne($connection->select()
        ->from($resource->getTableName('sales_order'), ['COUNT(*)'])->where('quote_id = ?', $quoteId));
    assertStockScenario($orderCount === (int) $persisted, 'Unexpected persisted order after forced failure.');
    assertStockScenario($ledger->getAttempt($quoteId)['state'] === ($persisted ? 'completed' : 'compensated'),
        'Unexpected durable ownership after forced failure.');
    foreach ($before as $code => $quantity) {
        assertStockScenario(FailingSubmissionObserver::$atFailure[$code] === $quantity - 1,
            "Missing reservation at failure for $code.");
        assertStockScenario($after[$code] === $quantity - (int) $persisted,
            "Unexpected stock after failure for $code.");
        $componentMovements = array_values(array_filter($movements,
            static fn (array $row): bool => $row['codigo'] === $code));
        $expectedTypes = $persisted ? ['reserva'] : ['reserva', 'compensacao'];
        $expectedQuantities = $persisted ? [-1] : [-1, 1];
        assertStockScenario(array_column($componentMovements, 'tipo') === $expectedTypes
            && array_map('intval', array_column($componentMovements, 'quantidade')) === $expectedQuantities,
            "Unexpected signed movements for $code.");
    }
    assertStockScenario($connection->getTransactionLevel() === 0, 'Checkout leaked transaction.');
    echo json_encode(['event' => $event, 'database' => $database, 'quote_id' => $quoteId,
        'orders' => $orderCount, 'before' => $before,
        'at_failure' => FailingSubmissionObserver::$atFailure, 'after' => $after,
        'state_at_failure' => FailingSubmissionObserver::$stateAtFailure,
        'final_state' => $ledger->getAttempt($quoteId)['state'],
        'movements' => $movements,
        'balances' => $ledger->getNetQuantitiesByQuoteId($quoteId)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    echo 'PASS: ', $event, $persisted ? ' preserves persisted order stock' : ' compensates all seven components', "\n";
} elseif ($command === 'reserve' || $command === 'reserve-held' || $command === 'compensate') {
    $quoteId = (int) ($argv[2] ?? 0);
    $qty = (int) ($argv[3] ?? 1);
    if ($command === 'reserve' || $command === 'reserve-held') {
        if ($command === 'reserve-held') {
            $connection->beginTransaction();
        }
        $quoteStock->reserveForQuote('SOL-LUA-HOLO', $qty, $quoteId);
        if ($command === 'reserve-held') {
            echo "LOCKED\n";
            flush();
            usleep(1000000);
            $connection->commit();
        }
    } else {
        $quoteStock->compensateForQuote('SOL-LUA-HOLO', $qty, $quoteId);
    }
    echo "PASS: $command\n";
} elseif ($command === 'adjust') {
    $stock->adjust('SOL-LUA-HOLO', (int) ($argv[2] ?? 10), 'F2.3 isolated integration fixture');
    echo "PASS: adjust\n";
} elseif ($command === 'state') {
    echo json_encode([
        'stock' => $repository->getByCodigo('SOL-LUA-HOLO')->getEstoque(),
        'ledger' => $connection->fetchAll(
            $connection->select()->from(['m' => $ledger->getMainTable()], ['tipo', 'quantidade', 'quote_id'])
                ->join(['c' => $resource->getTableName('nuvem_atelie_componente')], 'c.componente_id = m.componente_id', [])
                ->where('c.codigo = ?', 'SOL-LUA-HOLO')
        ),
    ], JSON_THROW_ON_ERROR), "\n";
} else {
    throw new InvalidArgumentException('Unknown integration scenario.');
}
