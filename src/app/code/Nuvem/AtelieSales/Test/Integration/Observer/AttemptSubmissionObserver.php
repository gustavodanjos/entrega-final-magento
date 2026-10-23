<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Integration\Observer;

use LogicException;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteManagement;
use Nuvem\AtelieSales\Model\QuoteStockReservation;

class AttemptSubmissionObserver implements ObserverInterface
{
    /** @var string */
    public static string $mode = 'none';

    /** @var int */
    public static int $rejections = 0;

    public function execute(Observer $observer): void
    {
        if (self::$mode === 'failure') {
            throw new LocalizedException(__('F2.3 integration: forced attempt failure.'));
        }
        if (self::$mode === 'duplicate-reservation') {
            $manager = ObjectManager::getInstance();
            $quote = $observer->getEvent()->getQuote();
            $reservation = $manager->get(QuoteStockReservation::class);
            $attemptId = $reservation->getAttemptId($quote);
            assertStockScenario($attemptId !== null, 'Duplicate observer lost the current owner.');
            $resource = $manager->get(\Magento\Framework\App\ResourceConnection::class);
            $table = $resource->getTableName('nuvem_atelie_movimentacao');
            $connection = $resource->getConnection();
            $before = (int) $connection->fetchOne($connection->select()->from($table, ['COUNT(*)'])
                ->where('quote_id = ?', $quote->getId()));
            $reservation->reserve($quote, $attemptId);
            $after = (int) $connection->fetchOne($connection->select()->from($table, ['COUNT(*)'])
                ->where('quote_id = ?', $quote->getId()));
            assertStockScenario($before === 7 && $after === $before, 'Repeated observer changed the ledger.');
            return;
        }
        if (self::$mode === 'concurrent') {
            $manager = ObjectManager::getInstance();
            $quoteId = (int) $observer->getEvent()->getQuote()->getId();
            $resource = $manager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            $table = $resource->getTableName('nuvem_atelie_movimentacao');
            $query = $connection->select()->from($table)->where('quote_id = ?', $quoteId);
            $before = $connection->fetchAll($query);
            \assertStockScenario(count($before) === 7, 'Owner must reserve before competitor starts.');
            $results = \ConcurrentStockScenarios::run([['submit', (string) $quoteId]]);
            \assertStockScenario($results[0]['exit_code'] === 0, $results[0]['output']);
            $result = json_decode($results[0]['output'], true, 512, JSON_THROW_ON_ERROR);
            \assertStockScenario($result['outcome'] === 'duplicate', 'Competitor was not rejected.');
            \assertStockScenario($connection->fetchAll($query) === $before, 'Competitor changed owner ledger.');
            return;
        }
        if (self::$mode !== 'reentry') {
            return;
        }
        $manager = ObjectManager::getInstance();
        $quoteId = (int) $observer->getEvent()->getQuote()->getId();
        $quote = $manager->create(CartRepositoryInterface::class)->get($quoteId);
        try {
            $manager->get(QuoteManagement::class)->submit($quote);
            throw new LogicException('Expected reentry rejection.');
        } catch (LocalizedException $exception) {
            assertStockScenario(
                str_contains($exception->getMessage(), 'já está sendo finalizado'),
                'Unexpected nested checkout error: ' . $exception->getMessage()
            );
            assertStockScenario(
                $manager->get(QuoteStockReservation::class)->hasReservation($quote),
                'Reentry cleared the outer reservation ownership.'
            );
            self::$rejections++;
        }
    }
}
