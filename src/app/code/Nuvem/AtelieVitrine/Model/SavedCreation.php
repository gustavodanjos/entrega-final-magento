<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Model;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\DataObject\IdentityInterface;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation as SavedCreationResource;

/**
 * Saved customer creation model.
 */
class SavedCreation extends AbstractModel implements IdentityInterface
{

    public const CACHE_TAG = 'nuvem_atelie_public_creations';

    /**
     * Return the cache identities for saved creations.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG, self::CACHE_TAG . '_' . $this->getId()];
    }

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
