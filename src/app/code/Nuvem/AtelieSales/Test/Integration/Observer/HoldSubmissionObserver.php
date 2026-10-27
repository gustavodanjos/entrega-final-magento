<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Integration\Observer;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class HoldSubmissionObserver implements ObserverInterface
{
    public static string $directory = '';

    public function execute(Observer $observer): void
    {
        $connection = ObjectManager::getInstance()->get(ResourceConnection::class)->getConnection();
        \assertStockScenario($connection->getTransactionLevel() === 1, 'Reservation must retain its root transaction.');
        file_put_contents(self::$directory . '/reserved', (string) $observer->getEvent()->getQuote()->getId());
        $deadline = microtime(true) + 15;
        while (!is_file(self::$directory . '/release')) {
            \assertStockScenario(microtime(true) < $deadline, 'Timed out waiting to release the checkout.');
            usleep(10000);
        }
    }
}
