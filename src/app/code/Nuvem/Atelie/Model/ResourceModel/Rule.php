<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Rule
 *
 * Resource model for customization rule.
 */
class Rule extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('nuvem_atelie_regra', 'regra_id');
    }
}
