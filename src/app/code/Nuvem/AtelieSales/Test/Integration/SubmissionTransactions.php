<?php
declare(strict_types=1);

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;

$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$codes = array_column($payload['configuracao']['selecoes'], 'componente');
$before = $fixture->stocks($codes);
assertStockScenario(count($before) === 7 && min($before) >= 1, 'Insufficient fixture stock.');
$quoteId = $fixture->create($payload);
$quotes = $objectManager->get(CartRepositoryInterface::class);
$orderQuery = $connection->select()->from($resource->getTableName('sales_order'), ['COUNT(*)'])
    ->where('quote_id = ?', $quoteId);

if (in_array($command, ['outer-commit', 'outer-rollback'], true)) {
    $connection->beginTransaction();
    try {
        $order = $objectManager->get(QuoteManagement::class)->submit($quotes->get($quoteId));
        assertStockScenario((bool) $order?->getId(), 'Nested checkout did not save its order.');
        assertStockScenario($connection->getTransactionLevel() === 1, 'Checkout closed the caller transaction.');
        if ($command === 'outer-commit') {
            $connection->commit();
        } else {
            $connection->rollBack();
        }
    } finally {
        while ($connection->getTransactionLevel() > 0) {
            $connection->rollBack();
        }
    }
    $consumed = (int) ($command === 'outer-commit');
    assertStockScenario((int) $connection->fetchOne($orderQuery) === $consumed, 'Order ignored the caller rollback.');
    assertStockScenario(count($fixture->movements([$quoteId])) === 7 * $consumed, 'Ledger ignored caller rollback.');
    $after = $fixture->stocks($codes);
    foreach ($before as $code => $qty) {
        assertStockScenario($after[$code] === $qty - $consumed, 'Stock ignored caller transaction.');
    }
} else {
    $directory = sys_get_temp_dir() . '/f23-submission-' . bin2hex(random_bytes(8));
    assertStockScenario(mkdir($directory, 0700), 'Cannot create submission synchronization directory.');
    try {
        $commands = [['submit-held', (string) $quoteId, $directory]];
        if ($command === 'active-owner') {
            $commands[] = ['submit-after-reservation', (string) $quoteId, $directory];
        }
        $results = ConcurrentStockScenarios::run($commands,
            function (array $workers) use ($directory, $command, $fixture, $codes, $before, $quoteId,
                $connection, $orderQuery, $resource): void {
                $ready = $command === 'active-owner' ? '/competing' : '/reserved';
                $deadline = microtime(true) + 15;
                while (!is_file($directory . $ready)) {
                    foreach ($workers as $worker) {
                        assertStockScenario(proc_get_status($worker['process'])['running'],
                            file_get_contents($worker['output']));
                    }
                    assertStockScenario(microtime(true) < $deadline, 'Workers did not reach reservation.');
                    usleep(10000);
                }
                assertStockScenario($fixture->stocks($codes) === $before, 'Uncommitted stock escaped checkout.');
                assertStockScenario($fixture->movements([$quoteId]) === [], 'Uncommitted ledger escaped checkout.');
                assertStockScenario((int) $connection->fetchOne($orderQuery) === 0, 'Order committed before placement.');
                $state = $connection->fetchOne($connection->select()
                    ->from($resource->getTableName('quote'), 'atelie_stock_attempt_state')
                    ->where('entity_id = ?', $quoteId));
                assertStockScenario($state === null, 'Ownership committed independently of stock and order.');
                if ($command === 'active-owner') {
                    usleep(200000);
                    assertStockScenario(proc_get_status($workers[1]['process'])['running'],
                        'Competitor did not wait for the quote lock.');
                    touch($directory . '/release');
                } else {
                    assertStockScenario(proc_terminate($workers[0]['process'], 9), 'Could not terminate test owner.');
                }
            });
        if ($command === 'active-owner') {
            foreach ($results as $result) {
                assertStockScenario($result['exit_code'] === 0, $result['output']);
            }
            $outcomes = array_map(static fn (array $result): string =>
                json_decode($result['output'], true, 512, JSON_THROW_ON_ERROR)['outcome'], $results);
            assertStockScenario($outcomes === ['ordered', 'duplicate'], 'Quote lock did not select a single owner.');
        } else {
            assertStockScenario($results[0]['exit_code'] !== 0, 'Owner was not terminated.');
            assertStockScenario(!$ledger->hasOrder($quoteId), 'Terminated checkout committed an order.');
            assertStockScenario($fixture->stocks($codes) === $before, 'Terminated checkout lost stock.');
            assertStockScenario($fixture->movements([$quoteId]) === [], 'Terminated checkout left ledger rows.');
            assertStockScenario($ledger->getAttempt($quoteId)['id'] === null, 'Terminated checkout retained ownership.');
            $order = $objectManager->get(QuoteManagement::class)->submit($quotes->get($quoteId));
            assertStockScenario((bool) $order?->getId(), 'Quote was not reusable after process termination.');
        }
        assertStockScenario((int) $connection->fetchOne($orderQuery) === 1, 'Expected one committed order.');
        assertStockScenario(count($fixture->movements([$quoteId])) === 7, 'Expected one reservation per component.');
        $after = $fixture->stocks($codes);
        foreach ($before as $code => $qty) {
            assertStockScenario($after[$code] === $qty - 1, 'Checkout consumed stock more than once.');
        }
    } finally {
        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
}
assertStockScenario($connection->getTransactionLevel() === 0, 'Submission leaked a transaction.');
echo 'PASS: ', $command, " preserves the shared stock/order transaction\n";
