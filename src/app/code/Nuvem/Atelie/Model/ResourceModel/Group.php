<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Group
 *
 * Resource model for customization group.
 */
class Group extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('nuvem_atelie_grupo', 'grupo_id');
    }
}
