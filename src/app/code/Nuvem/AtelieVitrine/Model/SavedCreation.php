<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Model;

use Magento\Framework\Model\AbstractModel;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation as SavedCreationResource;

/**
 * Saved customer creation model.
 */
class SavedCreation extends AbstractModel
{
    /**
     * Initialize the resource model.
     *
     * @return void
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _construct(): void
    {
        $this->_init(SavedCreationResource::class);
    }
}
