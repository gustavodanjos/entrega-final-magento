<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Component
 *
 * Resource model for customization component.
 */
class Component extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('nuvem_atelie_componente', 'componente_id');
    }
}
