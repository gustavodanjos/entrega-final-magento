<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\ComponentFactory;
use Nuvem\Atelie\Model\ComponentSearchResultsFactory;
use Nuvem\Atelie\Model\ResourceModel\Component as ComponentResource;
use Nuvem\Atelie\Model\ResourceModel\Component\CollectionFactory as ComponentCollectionFactory;

/**
 * Class ComponentRepository
 *
 * Repository implementation for managing customization components.
 */
class ComponentRepository implements ComponentRepositoryInterface
{
    /**
     * @var ComponentResource
     */
    private ComponentResource $resource;

    /**
     * @var ComponentFactory
     */
    private ComponentFactory $componentFactory;

    /**
     * @var ComponentCollectionFactory
     */
    private ComponentCollectionFactory $collectionFactory;

    /**
     * @var ComponentSearchResultsFactory
     */
    private ComponentSearchResultsFactory $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @param ComponentResource $resource
     * @param ComponentFactory $componentFactory
     * @param ComponentCollectionFactory $collectionFactory
     * @param ComponentSearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        ComponentResource $resource,
        ComponentFactory $componentFactory,
        ComponentCollectionFactory $collectionFactory,
        ComponentSearchResultsFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor
    ) {
        $this->resource = $resource;
        $this->componentFactory = $componentFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
    }

    /**
     * Save component
     *
     * @param ComponentInterface $component
     * @return ComponentInterface
     * @throws CouldNotSaveException
     */
    public function save(ComponentInterface $component): ComponentInterface
    {
        try {
            $this->resource->save($component);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save component: %1', $exception->getMessage()),
                $exception
            );
        }

        return $component;
    }

    /**
     * Get component by ID
     *
     * @param int $componentId
     * @return ComponentInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $componentId): ComponentInterface
    {
        $component = $this->componentFactory->create();
        $this->resource->load($component, $componentId);
        if (!$component->getId()) {
            throw new NoSuchEntityException(
                __('Component with ID "%1" does not exist.', $componentId)
            );
        }

        return $component;
    }

    /**
     * Get component by code
     *
     * @param string $codigo
     * @return ComponentInterface
     * @throws NoSuchEntityException
     */
    public function getByCodigo(string $codigo): ComponentInterface
    {
        $component = $this->componentFactory->create();
        $this->resource->load($component, $codigo, ComponentInterface::CODIGO);
        if (!$component->getId()) {
            throw new NoSuchEntityException(
                __('Component with code "%1" does not exist.', $codigo)
            );
        }

        return $component;
    }

    /**
     * Get component by code (alias for getByCodigo)
     *
     * @param string $code
     * @return ComponentInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): ComponentInterface
    {
        return $this->getByCodigo($code);
    }

    /**
     * Delete component
     *
     * @param ComponentInterface $component
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ComponentInterface $component): bool
    {
        try {
            $this->resource->delete($component);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete component: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * Delete component by ID
     *
     * @param int $componentId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $componentId): bool
    {
        return $this->delete($this->getById($componentId));
    }

    /**
     * Retrieve components matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return ComponentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ComponentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var ComponentSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
