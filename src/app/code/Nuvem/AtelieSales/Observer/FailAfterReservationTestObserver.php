<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;


class FailAfterReservationTestObserver implements ObserverInterface
{
    private const TEST_FAIL_ENV_VAR = 'NUVEM_ATELIE_TEST_FAIL_AFTER_RESERVE';

    /**
     * Force a failure after the reservation when the test flag is set
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException when the test flag is set
     */
    public function execute(Observer $observer): void
    {
        if (getenv(self::TEST_FAIL_ENV_VAR) === '1') {
            throw new LocalizedException(
                __('[Teste F2.3] Falha forçada depois da reserva: a compensação deve devolver o estoque.')
            );
        }
    }
}
