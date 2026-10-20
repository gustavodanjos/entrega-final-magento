<?php

/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */

declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Model;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation as SavedCreationResource;

/**
 * Handles persistence and lookup operations for saved creations.
 */
class SavedCreationRepository
{
    /**
     * @param SavedCreationResource $resource
     * @param SavedCreationFactory $creationFactory
     */
    public function __construct(
        private readonly SavedCreationResource $resource,
        private readonly SavedCreationFactory $creationFactory
    ) {
    }

    /**
     * Persist a saved creation.
     *
     * @param SavedCreation $creation
     * @return SavedCreation
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function save(SavedCreation $creation): SavedCreation
    {
        $this->resource->save($creation);

        return $creation;
    }

    /**
     * Find a saved creation by its public hash.
     *
     * Access authorization must be handled by the calling service.
     *
     * @param string $hash
     * @return SavedCreation
     * @throws NoSuchEntityException
     */
    public function getByHash(string $hash): SavedCreation
    {
        $creation = $this->creationFactory->create();

        $this->resource->load($creation, $hash, 'hash');

        if (!$creation->getId()) {
            throw new NoSuchEntityException(
                __('Saved creation was not found.')
            );
        }

        return $creation;
    }
}
