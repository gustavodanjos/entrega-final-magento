<?php
declare(strict_types=1);

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

final class StaleOrderAttemptObserver implements \Magento\Framework\Event\ObserverInterface
{
    public function execute(\Magento\Framework\Event\Observer $observer): void
    {
        $observer->getEvent()->getOrder()->setData(QuoteStockReservation::ORDER_ATTEMPT_ID, 'old-owner');
    }
}

$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$codes = array_column($payload['configuracao']['selecoes'], 'componente');
$before = $fixture->stocks($codes);
assertStockScenario(count($before) === 7 && min($before) >= 1, 'Insufficient fixture stock.');
$quoteId = $fixture->create($payload);
$quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteId);
$reservation = $objectManager->get(QuoteStockReservation::class);
if ($command === 'partial-reservation') {
    $stock->adjust('SOL-LUA-HOLO', 0, 'F2.3 partial reservation probe');
    $unavailableStock = $fixture->stocks($codes);
    try {
        $objectManager->get(\Magento\Quote\Model\QuoteManagement::class)->submit($quote);
        throw new LogicException('Expected insufficient component stock.');
    } catch (LocalizedException $exception) {
        assertStockScenario(str_contains($exception->getMessage(), 'esgotou enquanto'), $exception->getMessage());
        assertStockScenario($fixture->stocks($codes) === $unavailableStock, 'Partial reservation lost stock.');
        assertStockScenario($fixture->movements([$quoteId]) === [], 'Partial reservation left ledger rows.');
        assertStockScenario($ledger->getAttempt($quoteId)['state'] === null, 'Failed ownership did not roll back.');
    } finally {
        $stock->adjust('SOL-LUA-HOLO', $before['SOL-LUA-HOLO'], 'F2.3 partial reservation cleanup');
    }
} elseif ($command === 'stale-order') {
    $events = $objectManager->get(\Magento\Framework\Event\Config\Data::class);
    $event = 'sales_order_save_before';
    $events->get($event);
    $events->merge([$event => ['f23_stale_order' => [
        'name' => 'f23_stale_order', 'instance' => StaleOrderAttemptObserver::class,
    ]]]);
    try {
        $objectManager->get(\Magento\Quote\Model\QuoteManagement::class)->submit($quote);
        throw new LogicException('Expected stale order rejection.');
    } catch (LocalizedException $exception) {
        assertStockScenario(str_contains($exception->getMessage(), 'já está sendo finalizado'), $exception->getMessage());
        assertStockScenario(!$ledger->hasOrder($quoteId), 'Stale order was persisted.');
        assertStockScenario($ledger->getAttempt($quoteId)['state'] === null, 'Ownership did not roll back.');
        $movements = $fixture->movements([$quoteId]);
        assertStockScenario($movements === [], 'Failed order left stock movements.');
        foreach ($ledger->getNetQuantitiesByQuoteId($quoteId) as $balance) {
            assertStockScenario($balance['net'] === 0, 'Stale order left an outstanding balance.');
        }
    }
} elseif ($command === 'legacy-balance') {
    $quoteStock->reserveForQuote($codes[0], 1, $quoteId);
    $movements = $fixture->movements([$quoteId]);
    try {
        $reservation->beginAttempt($quote);
        throw new LogicException('Expected legacy balance rejection.');
    } catch (LocalizedException) {
        assertStockScenario($fixture->movements([$quoteId]) === $movements, 'Legacy balance was recovered.');
    }
    $quoteStock->compensateForQuote($codes[0], 1, $quoteId);
} else {
    $oldId = $reservation->beginAttempt($quote);
    $reservation->reserve($quote, $oldId);
    $reservation->markFailed($quote, $oldId);
    $reservation->compensate($quote, $oldId);
    $reservation->endAttempt($quote, $oldId);
    $newId = $reservation->beginSubmission($quote);
    $reservation->reserve($quote, $newId);
    $movements = $fixture->movements([$quoteId]);
    $pendingStock = $fixture->stocks($codes);
    $failedOrder = $objectManager->create(\Magento\Sales\Model\Order::class);
    $failedOrder->setQuoteId($quoteId)->setData(QuoteStockReservation::ORDER_ATTEMPT_ID, $oldId);
    $objectManager->get(\Magento\Framework\Event\ManagerInterface::class)->dispatch(
        'sales_model_service_quote_submit_failure',
        ['quote' => $quote, 'order' => $failedOrder, 'exception' => new \RuntimeException('Delayed old failure.')]
    );
    $reservation->markFailed($quote, $oldId);
    $reservation->compensate($quote, $oldId);
    assertStockScenario($ledger->getAttempt($quoteId) === ['id' => $newId, 'state' => 'active'],
        'Old failure changed new ownership.');
    assertStockScenario($fixture->movements([$quoteId]) === $movements, 'Old failure changed new ledger.');
    assertStockScenario($fixture->stocks($codes) === $pendingStock, 'Old failure released new stock.');
    $reservation->handleFailure($quote, $newId, new RuntimeException('Finish current test attempt.'));
    $reservation->endAttempt($quote, $newId);
}
assertStockScenario($fixture->stocks($codes) === $before, 'Fixture stock was not restored.');
assertStockScenario($connection->getTransactionLevel() === 0, 'Ownership scenario leaked a transaction.');
echo 'PASS: ', $command, "\n";
