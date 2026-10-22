<?php
declare(strict_types=1);

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

if ($command === 'submit') {
    $quoteId = (int) ($argv[2] ?? 0);
    try {
        $quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteId);
        $order = $objectManager->get(QuoteManagement::class)->submit($quote);
        assertStockScenario($order !== null && $order->getId(), 'Checkout did not persist an order.');
        $result = ['quote_id' => $quoteId, 'outcome' => 'ordered', 'order_id' => (int) $order->getId()];
    } catch (Throwable $exception) {
        $message = $exception->getMessage();
        $outcome = 'unexpected_error';
        if ($exception instanceof \Magento\Framework\Exception\LocalizedException) {
            if (str_contains($message, 'esgotou enquanto')) {
                $outcome = 'out_of_stock';
            } elseif (str_contains($message, 'já está sendo finalizado')) {
                $outcome = 'duplicate';
            }
        }
        $result = ['quote_id' => $quoteId, 'outcome' => $outcome, 'message' => $message];
    }
    echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
    exit($result['outcome'] === 'unexpected_error' ? 1 : 0);
}

if ($command === 'compensate-quote') {
    $quoteId = (int) ($argv[2] ?? 0);
    $quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteId);
    $objectManager->get(QuoteStockReservation::class)->compensate($quote);
    echo json_encode(['quote_id' => $quoteId, 'outcome' => 'compensated'], JSON_THROW_ON_ERROR), "\n";
    return;
}

$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
assertStockScenario(is_array($payload), 'Pass the official fixture JSON through stdin.');
$codes = array_column($payload['configuracao']['selecoes'] ?? [], 'componente');
assertStockScenario(count(array_unique($codes)) === 7 && in_array('SOL-LUA-HOLO', $codes, true),
    'Expected the official seven-component Lua fixture.');
if ($command === 'reset-fixture') {
    foreach ($codes as $code) {
        $stock->adjust($code, 10, 'F2.3 isolated fixture preparation');
    }
    echo json_encode(['database' => $database, 'stock' => $fixture->stocks($codes)],
        JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    echo "PASS: reset-fixture\n";
    return;
}
if ($command === 'race') {
    $stock->adjust('SOL-LUA-HOLO', 10, 'F2.3 isolated race preparation');
}
$before = $fixture->stocks($codes);
$required = $command === 'race' ? 10 : 1;
assertStockScenario(count($before) === 7 && min($before) >= $required, 'Insufficient fixture component stock.');
$count = $command === 'race' ? 40 : 1;
$quoteIds = [];
for ($index = 0; $index < $count; $index++) {
    $quoteIds[] = $fixture->create($payload);
}
if ($command === 'prepare') {
    echo json_encode(['quote_ids' => $quoteIds, 'stock' => $before], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
    return;
}

if ($command === 'concurrent-compensation') {
    $quote = $objectManager->get(CartRepositoryInterface::class)->get($quoteIds[0]);
    $objectManager->get(QuoteStockReservation::class)->reserve($quote);
    $workers = array_fill(0, 8, ['compensate-quote', (string) $quoteIds[0]]);
} elseif ($command === 'duplicate') {
    $workers = array_fill(0, 2, ['submit', (string) $quoteIds[0]]);
} else {
    $workers = array_map(static fn (int $id): array => ['submit', (string) $id], $quoteIds);
}
$results = ConcurrentStockScenarios::run($workers);
$decoded = [];
foreach ($results as $result) {
    assertStockScenario($result['exit_code'] === 0, 'Worker failed: ' . $result['output']);
    $decoded[] = json_decode($result['output'], true, 512, JSON_THROW_ON_ERROR);
}
$after = $fixture->stocks($codes);
$movements = $fixture->movements($quoteIds);
$summary = [];
foreach ($codes as $code) {
    $rows = array_filter($movements, static fn (array $row): bool => $row['codigo'] === $code);
    $summary[$code] = ['before' => $before[$code], 'after' => $after[$code],
        'movements' => count($rows), 'net' => array_sum(array_column($rows, 'quantidade'))];
}
$orders = $connection->fetchCol($connection->select()->from($resource->getTableName('sales_order'), 'entity_id')
    ->where('quote_id IN (?)', $quoteIds));
$report = ['scenario' => $command, 'database' => $database, 'quote_ids' => $quoteIds,
    'orders' => count($orders), 'outcomes' => array_count_values(array_column($decoded, 'outcome')),
    'components' => $summary, 'workers' => $decoded];
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";
if ($command === 'concurrent-compensation') {
    assertStockScenario($orders === [], 'Compensation fixture unexpectedly persisted an order.');
    $objectManager->get(QuoteStockReservation::class)->compensate($quote);
    assertStockScenario($fixture->movements($quoteIds) === $movements, 'Repeated compensation added movements.');
    foreach ($summary as $row) {
        assertStockScenario($row['before'] === $row['after'] && $row['net'] === 0 && $row['movements'] === 2,
            'Concurrent compensation must restore each component exactly once.');
    }
} else {
    $expectedOrders = $command === 'race' ? 10 : 1;
    assertStockScenario(count($orders) === $expectedOrders, 'Unexpected persisted order count.');
    assertStockScenario(($report['outcomes']['ordered'] ?? 0) === $expectedOrders, 'Unexpected successful workers.');
    $rejection = $command === 'race' ? 'out_of_stock' : 'duplicate';
    assertStockScenario(($report['outcomes'][$rejection] ?? 0) === count($workers) - $expectedOrders,
        'Unexpected checkout refusal reason.');
    foreach ($summary as $row) {
        assertStockScenario($row['before'] - $row['after'] === $expectedOrders
            && $row['net'] === -$expectedOrders && $row['movements'] === $expectedOrders,
            'Stock and ledger did not reconcile.');
    }
}
echo "PASS: $command\n";
