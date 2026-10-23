<?php
declare(strict_types=1);

use Magento\Framework\Event\Config\Data as EventConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Model\QuoteStockReservation;
use Nuvem\AtelieSales\Test\Integration\Model\RecoverableQuoteStock;
use Nuvem\AtelieSales\Test\Integration\Observer\AttemptSubmissionObserver;

require __DIR__ . '/Model/RecoverableQuoteStock.php';
require __DIR__ . '/Observer/AttemptSubmissionObserver.php';

$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$codes = array_column($payload['configuracao']['selecoes'], 'componente');
$before = $fixture->stocks($codes);
assertStockScenario(count($before) === 7 && min($before) >= 1, 'Insufficient fixture stock.');
$quoteId = $fixture->create($payload);
$quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteId);
$orders = $resource->getTableName('sales_order');
$orderQuery = $connection->select()->from($orders, ['COUNT(*)'])->where('quote_id = ?', $quoteId);

if ($command === 'order-snapshot') {
    $connection->beginTransaction();
    try {
        assertStockScenario((int) $connection->fetchOne($orderQuery) === 0, 'Expected an empty order snapshot.');
        $results = ConcurrentStockScenarios::run([['submit', (string) $quoteId]]);
        assertStockScenario($results[0]['exit_code'] === 0, $results[0]['output']);
        $result = json_decode($results[0]['output'], true, 512, JSON_THROW_ON_ERROR);
        assertStockScenario($result['outcome'] === 'ordered', 'Worker did not persist an order.');
        assertStockScenario(
            (int) $connection->fetchOne($orderQuery) === 0,
            'Expected an older repeatable-read snapshot.'
        );
        $attempt = $ledger->getAttempt($quoteId);
        $ledger->saveAttempt($quoteId, $attempt['id'], QuoteStockReservation::STATE_PENDING);
        $objectManager->get(QuoteStockReservation::class)->compensate($quote, $attempt['id']);
        $connection->commit();
    } finally {
        while ($connection->getTransactionLevel() > 0) {
            $connection->rollBack();
        }
    }
} else {
    $events = $objectManager->get(EventConfig::class);
    $event = 'sales_model_service_quote_submit_before';
    $events->get($event);
    $events->merge([$event => ['f23_attempt_failure' => [
        'name' => 'f23_attempt_failure', 'instance' => AttemptSubmissionObserver::class,
    ]]]);
    if ($command === 'recovery') {
        $objectManager->configure([
            QuoteStockReservation::class => ['arguments' => [
                'componentStock' => ['instance' => RecoverableQuoteStock::class],
            ]],
        ]);
        $recoverableStock = $objectManager->get(RecoverableQuoteStock::class);
        $reservation = $objectManager->get(QuoteStockReservation::class);
        AttemptSubmissionObserver::$mode = 'failure';
        try {
            $objectManager->get(QuoteManagement::class)->submit($quote);
            throw new LogicException('Expected checkout failure.');
        } catch (LocalizedException $exception) {
            assertStockScenario(
                str_contains($exception->getMessage(), 'forced attempt failure'),
                'Original submission exception was replaced.'
            );
        }
        $pendingMovements = $fixture->movements([$quoteId]);
        assertStockScenario(count($pendingMovements) === 7, 'Failed compensation changed the ledger.');
        $quote = $objectManager->create(CartRepositoryInterface::class)->get($quoteId);
        assertStockScenario($reservation->hasReservation($quote), 'Quote reload lost pending compensation.');
        $pendingStock = $fixture->stocks($codes);
        foreach ($before as $code => $quantity) {
            assertStockScenario($pendingStock[$code] === $quantity - 1, 'Pending stock was unexpectedly released.');
        }
        try {
            $objectManager->get(QuoteManagement::class)->submit($quote);
            throw new LogicException('Expected recovery failure.');
        } catch (RuntimeException $exception) {
            assertStockScenario(
                str_contains($exception->getMessage(), 'compensation unavailable'),
                'New submission was not blocked by failed recovery.'
            );
        }
        assertStockScenario($reservation->hasReservation($quote), 'Failed recovery lost the pending reservation.');
        assertStockScenario($fixture->movements([$quoteId]) === $pendingMovements, 'Recovery failure added movements.');
        assertStockScenario((int) $connection->fetchOne($orderQuery) === 0, 'Recovery failure persisted an order.');
        $recoverableStock->failCompensation = false;
        AttemptSubmissionObserver::$mode = 'none';
    } else {
        AttemptSubmissionObserver::$mode = $command === 'active-owner' ? 'concurrent' : 'reentry';
    }
    $staleQuote = $objectManager->create(CartRepositoryInterface::class)->get($quoteId);
    $order = $objectManager->get(QuoteManagement::class)->submit($quote);
    $completedAttempt = $ledger->getAttempt($quoteId);
    $staleQuote->setData('atelie_stock_attempt_id', 'stale-owner');
    $staleQuote->setData('atelie_stock_attempt_state', QuoteStockReservation::STATE_ACTIVE);
    $staleQuote->setCustomerNote('F2.3 stale quote save probe');
    $objectManager->get(\Magento\Quote\Model\ResourceModel\Quote::class)->save($staleQuote);
    assertStockScenario($ledger->getAttempt($quoteId) === $completedAttempt, 'Stale quote overwrote ownership.');
    assertStockScenario((bool) $order?->getId(), 'Checkout did not persist an order.');
    assertStockScenario(
        !$objectManager->get(QuoteStockReservation::class)->hasReservation($quote),
        'Successful submission left pending reservation ownership.'
    );
    if ($command === 'reentry') {
        assertStockScenario(AttemptSubmissionObserver::$rejections === 1, 'Reentrant observer did not execute.');
    }
}

$after = $fixture->stocks($codes);
$movements = $fixture->movements([$quoteId]);
foreach ($before as $code => $quantity) {
    assertStockScenario($after[$code] === $quantity - 1, 'Stock must be consumed exactly once for ' . $code);
    $rows = array_values(array_filter($movements, static fn (array $row): bool => $row['codigo'] === $code));
    $types = $command === 'recovery' ? ['reserva', 'compensacao', 'reserva'] : ['reserva'];
    $quantities = $command === 'recovery' ? [-1, 1, -1] : [-1];
    assertStockScenario(
        array_column($rows, 'tipo') === $types
        && array_map('intval', array_column($rows, 'quantidade')) === $quantities,
        'Unexpected attempt movements for ' . $code
    );
}
assertStockScenario((int) $connection->fetchOne($orderQuery) === 1, 'Expected exactly one persisted order.');
assertStockScenario($connection->getTransactionLevel() === 0, 'Attempt handling leaked a transaction.');
fwrite(STDOUT, json_encode(['scenario' => $command, 'quote_id' => $quoteId, 'before' => $before,
    'after' => $after, 'movements' => $movements], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
fwrite(STDOUT, 'PASS: ' . $command . " preserves quote ownership and signed stock movements\n");
