<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Quote;

use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\ResourceModel\Quote;

class ProtectStockAttempt
{
    /**
     * Ownership is written under a database lock, never by a potentially stale quote object.
     *
     * @param Quote $subject
     * @param AbstractModel $quote
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSave(Quote $subject, AbstractModel $quote): array
    {
        $quote->unsetData('atelie_stock_attempt_id');
        $quote->unsetData('atelie_stock_attempt_state');
        return [$quote];
    }
}
