<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Nuvem\AtelieVitrine\Model\SavedCreation as SavedCreationModel;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation as SavedCreationResource;

/**
 * Collection of saved customer creations.
 */
class Collection extends AbstractCollection
{
    /**
     * Initialize the model and resource model.
     *
     * @return void
     */
    // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    protected function _construct(): void
    {
        $this->_init(
            SavedCreationModel::class,
            SavedCreationResource::class
        );
    }
}
