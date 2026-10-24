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
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\CollectionFactory;

/**
 * Handles persistence and lookup operations for saved creations.
 */
class SavedCreationRepository
{
    /**
     * @param SavedCreationResource $resource
     * @param SavedCreationFactory $creationFactory
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly SavedCreationResource $resource,
        private readonly SavedCreationFactory $creationFactory,
        private readonly CollectionFactory $collectionFactory
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
    
    /**
     * Return saved creations belonging to a customer.
     *
     * @param int $customerId
     * @return SavedCreation[]
     */
    public function getListByCustomerId(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $collection = $this->collectionFactory->create();

        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->setOrder('criacao_id', 'DESC');

        return array_values($collection->getItems());
    }

    
    /**
     * Delete a saved creation.
     *
     * @param SavedCreation $creation
     * @return void
     * @throws \Exception
     */
    public function delete(SavedCreation $creation): void
    {
        $this->resource->delete($creation);
    }

    /**
     * Find a creation owned by a specific customer.
     *
     * @param string $hash
     * @param int $customerId
     * @return SavedCreation
     * @throws NoSuchEntityException
     */
    public function getByHashAndCustomerId(
        string $hash,
        int $customerId
    ): SavedCreation {
        if ($customerId <= 0) {
            throw new NoSuchEntityException(
                __('Saved creation was not found.')
            );
        }

        $creation = $this->getByHash($hash);

        if ((int) $creation->getData('customer_id') !== $customerId) {
            throw new NoSuchEntityException(
                __('Saved creation was not found.')
            );
        }

        return $creation;
    }

}
