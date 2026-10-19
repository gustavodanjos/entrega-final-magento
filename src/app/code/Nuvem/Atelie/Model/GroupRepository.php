<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterfaceFactory;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Model\ResourceModel\Group as GroupResource;
use Nuvem\Atelie\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;

/**
 * Class GroupRepository
 *
 * Repository implementation for managing customization groups.
 */
class GroupRepository implements GroupRepositoryInterface
{
    /**
     * @var GroupResource
     */
    private GroupResource $resource;

    /**
     * @var GroupFactory
     */
    private GroupFactory $groupFactory;

    /**
     * @var GroupCollectionFactory
     */
    private GroupCollectionFactory $collectionFactory;

    /**
     * @var GroupSearchResultsInterfaceFactory
     */
    private GroupSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @param GroupResource $resource
     * @param GroupFactory $groupFactory
     * @param GroupCollectionFactory $collectionFactory
     * @param GroupSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        GroupResource $resource,
        GroupFactory $groupFactory,
        GroupCollectionFactory $collectionFactory,
        GroupSearchResultsInterfaceFactory $searchResultsFactory,
        ?CollectionProcessorInterface $collectionProcessor = null
    ) {
        $this->resource = $resource;
        $this->groupFactory = $groupFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor
            ?: ObjectManager::getInstance()->get(CollectionProcessorInterface::class);
    }

    /**
     * Save group
     *
     * @param GroupInterface $group
     * @return GroupInterface
     * @throws CouldNotSaveException
     */
    public function save(GroupInterface $group): GroupInterface
    {
        try {
            $this->resource->save($group);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save group: %1', $exception->getMessage()),
                $exception
            );
        }

        return $group;
    }

    /**
     * Get group by ID
     *
     * @param int $groupId
     * @return GroupInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $groupId): GroupInterface
    {
        $group = $this->groupFactory->create();
        $this->resource->load($group, $groupId);
        if (!$group->getId()) {
            throw new NoSuchEntityException(
                __('Group with ID "%1" does not exist.', $groupId)
            );
        }

        return $group;
    }

    /**
     * Get group by code
     *
     * @param string $codigo
     * @return GroupInterface
     * @throws NoSuchEntityException
     */
    public function getByCodigo(string $codigo): GroupInterface
    {
        $group = $this->groupFactory->create();
        $this->resource->load($group, $codigo, GroupInterface::CODIGO);
        if (!$group->getId()) {
            throw new NoSuchEntityException(
                __('Group with code "%1" does not exist.', $codigo)
            );
        }

        return $group;
    }

    /**
     * Get group by code (alias for getByCodigo)
     *
     * @param string $code
     * @return GroupInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): GroupInterface
    {
        return $this->getByCodigo($code);
    }

    /**
     * Delete group
     *
     * @param GroupInterface $group
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(GroupInterface $group): bool
    {
        try {
            $this->resource->delete($group);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete group: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * Delete group by ID
     *
     * @param int $groupId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $groupId): bool
    {
        return $this->delete($this->getById($groupId));
    }

    /**
     * Retrieve groups matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return GroupSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): GroupSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var GroupSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
