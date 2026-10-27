<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Persistence resource for saved creations.
 */
class SavedCreation extends AbstractDb
{
    /**
     * Initialize the database table and primary key.
     *
     * @return void
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _construct(): void
    {
        $this->_init('nuvem_atelie_criacao', 'criacao_id');
    }
}
