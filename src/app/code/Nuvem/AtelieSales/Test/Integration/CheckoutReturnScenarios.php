<?php
declare(strict_types=1);

use Magento\Framework\DB\Transaction;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Service\InvoiceService;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

require __DIR__ . '/CheckoutFixture.php';

function assertStockScenario(bool $condition, string $message): void
{
    \Nuvem\AtelieSales\Test\Integration\verifyReturn($condition, $message);
}

$fixture = new CheckoutFixture($objects);
$payload = ['sku' => 'NUVEM-RUNNER', 'qty' => 1, 'configuracao' => $configuration];
$codes = array_column($configuration['selecoes'], 'componente');
foreach (['cancel', 'refund'] as $operation) {
    $before = $fixture->stocks($codes);
    $cursor = $fixture->movementCursor();
    $quoteId = $fixture->create($payload);
    $quote = $objects->get(CartRepositoryInterface::class)->get($quoteId);
    $order = $objects->get(QuoteManagement::class)->submit($quote);
    assertStockScenario((bool) $order?->getId(), 'Checkout did not persist its order.');
    assertStockScenario($ledger->getAttempt($quoteId)['state'] === QuoteStockReservation::STATE_COMPLETED,
        'Checkout did not complete the stock attempt.');
    foreach ($fixture->stocks($codes) as $code => $quantity) {
        assertStockScenario($quantity === $before[$code] - 1, 'Checkout consumed an unexpected quantity.');
    }
    $orders = $objects->get(OrderRepositoryInterface::class);
    if ($operation === 'cancel') {
        assertStockScenario($objects->get(OrderManagementInterface::class)->cancel((int) $order->getId()),
            'The real checkout order could not be cancelled.');
        $order = $objects->create(OrderRepositoryInterface::class)->get((int) $order->getId());
        $orders->save($order);
    } else {
        $invoice = $objects->get(InvoiceService::class)->prepareInvoice($order);
        $invoice->setRequestedCaptureCase(Invoice::CAPTURE_OFFLINE);
        $invoice->register();
        $objects->create(Transaction::class)->addObject($invoice)->addObject($order)->save();
        $creditmemo = \Nuvem\AtelieSales\Test\Integration\savedCreditmemo($objects, $order, 1, true);
        $objects->get(\Magento\Sales\Model\ResourceModel\Order\Creditmemo::class)->save($creditmemo);
    }
    assertStockScenario($fixture->stocks($codes) === $before, 'Checkout return did not restore all components.');
    $item = $order->getAllVisibleItems()[0];
    $returned = $ledger->getReturnedQuantitiesByOrderItemId((int) $item->getId());
    assertStockScenario(count($returned) === 7 && array_sum($returned) === 7,
        'Checkout return was lost or repeated.');
    $delta = $fixture->movementSumsAfter($cursor, $codes);
    assertStockScenario(count($delta) === 7 && array_sum(array_map('abs', $delta)) === 0,
        'Checkout and return ledger did not reconcile.');
    assertStockScenario(count($fixture->movements([$quoteId])) === 7,
        'The completed order was compensated after its return.');
    assertStockScenario($connection->getTransactionLevel() === 0, 'Checkout return leaked a transaction.');
    echo "PASS: real checkout, $operation and repeated save reconcile all seven components\n";
}
