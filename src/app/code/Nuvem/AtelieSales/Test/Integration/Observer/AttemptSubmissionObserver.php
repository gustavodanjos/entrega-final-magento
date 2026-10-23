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
