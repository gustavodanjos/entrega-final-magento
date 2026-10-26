<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\Event\Config\Data as EventConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order as OrderResource;
use Magento\Sales\Model\ResourceModel\Order\Creditmemo as CreditmemoResource;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Model\OrderItemConfigurationReader;
use Nuvem\AtelieSales\Model\OrderItemStockReturner;
use Nuvem\AtelieSales\Model\ResourceModel\Movimentacao;

require dirname(__DIR__, 6) . '/app/bootstrap.php';

class FailingReturnPersistenceObserver implements ObserverInterface
{
    public static int $orderId = 0;
    public static bool $triggered = false;

    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getOrder();
        $creditmemo = $observer->getEvent()->getCreditmemo();
        $orderId = $order ? (int) $order->getId() : (int) $creditmemo?->getOrderId();
        if ($orderId === self::$orderId) {
            self::$triggered = true;
            throw new \RuntimeException('F2.4 forced persistence failure.');
        }
    }
}

function verifyReturn(bool $condition, string $message): void
{
    if (!$condition) {
        throw new \RuntimeException($message);
    }
}

function configuredOrder(ObjectManagerInterface $objects, int $quantity, int $invoiced = 0): Order
{
    $product = $objects->get(ProductRepositoryInterface::class)->get('NUVEM-RUNNER');
    $configuration = json_decode(file_get_contents(BP . '/app/code/Nuvem/AtelieSales/Test/Integration/_files/return-configuration.json'), true, 512, JSON_THROW_ON_ERROR);
    $item = $objects->create(Item::class);
    $item->setProductId($product->getId())->setProductType('atelie')->setSku($product->getSku())
        ->setName('F2.4 integration fixture')->setQtyOrdered($quantity)->setQtyInvoiced($invoiced)
        ->setPrice(10)->setBasePrice(10)->setRowTotal($quantity * 10)->setBaseRowTotal($quantity * 10)
        ->setProductOptions(['atelie_configuracao' => $configuration]);
    $payment = $objects->create(Payment::class)->setMethod('checkmo');
    $order = $objects->create(Order::class);
    $order->setStoreId(1)->setState($invoiced > 0 ? Order::STATE_PROCESSING : Order::STATE_NEW)
        ->setStatus($invoiced > 0 ? 'processing' : 'pending')->setCustomerIsGuest(true)
        ->setCustomerEmail('f24-integration@example.test')->setOrderCurrencyCode('BRL')->setBaseCurrencyCode('BRL')
        ->setBaseToOrderRate(1)->setGlobalCurrencyCode('BRL')->setBaseToGlobalRate(1)
        ->setSubtotal($quantity * 10)->setBaseSubtotal($quantity * 10)
        ->setGrandTotal($quantity * 10)->setBaseGrandTotal($quantity * 10)
        ->setTotalPaid($invoiced * 10)->setBaseTotalPaid($invoiced * 10)
        ->setTotalInvoiced($invoiced * 10)->setBaseTotalInvoiced($invoiced * 10)
        ->setTotalQtyOrdered($quantity)->addItem($item)->setPayment($payment);
    $objects->get(OrderResource::class)->save($order);
    $stock = $objects->get(ComponentStockInterface::class);
    foreach ($objects->get(OrderItemConfigurationReader::class)->getComponentQuantities($item) as $code => $qty) {
        $stock->reserve($code, $qty, (int) $order->getId(), (int) $item->getId());
    }
    return $order;
}

function savedCreditmemo(ObjectManagerInterface $objects, Order $order, int $quantity, bool $backToStock): Creditmemo
{
    $item = $order->getAllItems()[0];
    $creditmemo = $objects->get(CreditmemoFactory::class)->createByOrder($order, [
        'qtys' => [(int) $item->getId() => $quantity],
    ]);
    $creditmemo->setState(Creditmemo::STATE_OPEN);
    foreach ($creditmemo->getAllItems() as $creditmemoItem) {
        $creditmemoItem->setBackToStock($backToStock);
    }
    $objects->get(\Magento\Sales\Model\Service\CreditmemoService::class)->refund($creditmemo, true);
    return $creditmemo;
}

$database = getenv('F24_TEST_DATABASE');
verifyReturn(is_string($database) && preg_match('/^f24_validation_[a-z0-9_]+$/D', $database) === 1,
    'Set F24_TEST_DATABASE to an isolated, disposable f24_validation_* database.');
require __DIR__ . '/IsolatedEnvironment.php';
$objects = createIsolatedEnvironment($database);
verifyReturn($objects->get(DeploymentConfig::class)->get('db/connection/default/dbname') === $database,
    'Deployment configuration did not select the isolated database.');
$objects->get(State::class)->setAreaCode('frontend');
$resource = $objects->get(ResourceConnection::class);
$connection = $resource->getConnection();
$ledger = $objects->get(Movimentacao::class);
$returner = $objects->get(OrderItemStockReturner::class);
$stockQuantity = static fn (): int => (int) $connection->fetchOne($connection->select()
    ->from($resource->getTableName('nuvem_atelie_componente'), 'estoque')->where('codigo = ?', 'SOL-LUA-HOLO'));
$command = $argv[1] ?? 'scenarios';

if ($command === 'scenarios') {
    $configuration = json_decode(file_get_contents(__DIR__ . '/_files/return-configuration.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($configuration['selecoes'] as $selection) {
        $objects->get(ComponentStockInterface::class)->adjust($selection['componente'], 100, 'F2.4 isolated fixture');
    }
    $order = configuredOrder($objects, 3);
    $before = $stockQuantity();
    $order->cancel();
    verifyReturn($stockQuantity() === $before, 'Cancel returned stock before persistence.');
    $objects->get(OrderResource::class)->save($order);
    $returner->returnOrder($order);
    verifyReturn($stockQuantity() === $before + 3, 'Cancellation was lost or replayed.');
    $itemId = (int) $order->getAllItems()[0]->getId();
    verifyReturn(count($ledger->getReturnedQuantitiesByOrderItemId($itemId)) === 7, 'Expected seven cancellation movements.');
    echo "PASS: cancellation persists and returns seven components exactly once\n";

    $order = configuredOrder($objects, 3, 1);
    $before = $stockQuantity();
    $order->cancel();
    $objects->get(OrderResource::class)->save($order);
    verifyReturn($stockQuantity() === $before + 2, 'Cancellation returned invoiced units.');
    echo "PASS: partial cancellation keeps invoiced component units reserved\n";

    $order = configuredOrder($objects, 3, 3);
    $before = $stockQuantity();
    $creditmemo = savedCreditmemo($objects, $order, 1, true);
    $returner->returnCreditmemo($creditmemo);
    $objects->get(CreditmemoResource::class)->save($creditmemo->setCustomerNote('Repeated persistence'));
    verifyReturn($stockQuantity() === $before + 1, 'Repeated partial refund returned stock twice.');
    savedCreditmemo($objects, $order, 1, true);
    verifyReturn($stockQuantity() === $before + 2, 'Distinct partial refund did not return stock.');
    savedCreditmemo($objects, $order, 1, false);
    verifyReturn($stockQuantity() === $before + 2, 'Refund ignored back_to_stock flag.');
    echo "PASS: partial refunds, repeated saves and back_to_stock selection\n";

    require __DIR__ . '/CheckoutReturnScenarios.php';

    $events = $objects->get(EventConfig::class);
    $events->get('sales_order_save_after');
    $events->merge(['sales_order_save_after' => ['f24_failure' => [
        'name' => 'f24_failure', 'instance' => FailingReturnPersistenceObserver::class,
    ]]]);
    $order = configuredOrder($objects, 1);
    $before = $stockQuantity();
    FailingReturnPersistenceObserver::$orderId = (int) $order->getId();
    $order->cancel();
    try {
        $objects->get(OrderResource::class)->save($order);
        throw new \LogicException('Expected order persistence failure.');
    } catch (\RuntimeException $exception) {
        verifyReturn($exception->getMessage() === 'F2.4 forced persistence failure.', $exception->getMessage());
    } finally {
        FailingReturnPersistenceObserver::$orderId = 0;
    }
    verifyReturn($stockQuantity() === $before, 'Failed order save returned stock.');
    verifyReturn($ledger->getReturnedQuantitiesByOrderItemId((int) $order->getAllItems()[0]->getId()) === [],
        'Failed order save left return movements.');
    $persistedItem = $connection->fetchRow($connection->select()->from($resource->getTableName('sales_order_item'))
        ->where('item_id = ?', (int) $order->getAllItems()[0]->getId()));
    verifyReturn((int) $persistedItem['qty_canceled'] === 0, 'Failed cancellation was persisted.');
    verifyReturn($connection->getTransactionLevel() === 0, 'Return flow leaked a transaction.');
    echo "PASS: failed cancellation persistence rolls back stock and ledger\n";

    $events->get('sales_order_creditmemo_save_after');
    $events->merge(['sales_order_creditmemo_save_after' => ['f24_creditmemo_failure' => [
        'name' => 'f24_creditmemo_failure', 'instance' => FailingReturnPersistenceObserver::class,
    ]]]);
    $order = configuredOrder($objects, 2, 2);
    $before = $stockQuantity();
    FailingReturnPersistenceObserver::$orderId = (int) $order->getId();
    FailingReturnPersistenceObserver::$triggered = false;
    try {
        savedCreditmemo($objects, $order, 1, true);
        throw new \LogicException('Expected creditmemo persistence failure.');
    } catch (\Magento\Framework\Exception\LocalizedException $exception) {
        verifyReturn(FailingReturnPersistenceObserver::$triggered, $exception->getMessage());
    } finally {
        FailingReturnPersistenceObserver::$orderId = 0;
    }
    verifyReturn($stockQuantity() === $before, 'Failed creditmemo save returned stock.');
    verifyReturn($ledger->getReturnedQuantitiesByOrderItemId((int) $order->getAllItems()[0]->getId()) === [],
        'Failed creditmemo save left return movements.');
    verifyReturn($connection->getTransactionLevel() === 0, 'Refund failure leaked a transaction.');
    echo "PASS: failed refund persistence rolls back stock and ledger\n";
} elseif ($command === 'create-race') {
    $order = configuredOrder($objects, 3, 3);
    $creditmemo = savedCreditmemo($objects, $order, 1, false);
    $second = ($argv[2] ?? '') === 'distinct' ? savedCreditmemo($objects, $order, 1, false) : $creditmemo;
    echo json_encode(['order_id' => (int) $order->getId(), 'creditmemo_id' => (int) $creditmemo->getId(),
        'second_id' => (int) $second->getId(), 'stock' => $stockQuantity()], JSON_THROW_ON_ERROR), "\n";
} elseif ($command === 'refund-held' || $command === 'refund-snapshot') {
    $creditmemo = $objects->create(Creditmemo::class);
    $objects->get(CreditmemoResource::class)->load($creditmemo, (int) ($argv[2] ?? 0));
    foreach ($creditmemo->getAllItems() as $item) {
        $item->setBackToStock(true);
    }
    $connection->beginTransaction();
    if ($command === 'refund-held') {
        $ledger->lockOrder((int) $creditmemo->getOrderId());
        echo "LOCKED\n";
        flush();
        usleep(2000000);
    } else {
        $connection->fetchOne('SELECT COUNT(*) FROM ' . $ledger->getMainTable());
    }
    $returner->returnCreditmemo($creditmemo);
    $connection->commit();
    echo "PASS: $command\n";
} elseif ($command === 'verify-race') {
    $order = $objects->get(OrderRepositoryInterface::class)->get((int) ($argv[2] ?? 0));
    $quantities = $ledger->getReturnedQuantitiesByOrderItemId((int) $order->getAllItems()[0]->getId());
    $expected = (int) ($argv[4] ?? 1);
    verifyReturn(count($quantities) === 7 && count(array_unique($quantities)) === 1
        && array_sum($quantities) === 7 * $expected, 'Concurrent refund ledger does not match its documents.');
    verifyReturn($stockQuantity() === (int) ($argv[3] ?? 0) + $expected, 'Concurrent refund stock does not match ledger.');
    echo "PASS: concurrent refunds return seven components for $expected document(s) despite a stale snapshot\n";
} else {
    throw new \InvalidArgumentException('Unknown return scenario.');
}
