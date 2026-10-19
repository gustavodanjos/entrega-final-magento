<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterfaceFactory;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\ResourceModel\Rule as RuleResource;
use Nuvem\Atelie\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;

/**
 * Class RuleRepository
 *
 * Repository implementation for managing customization rules.
 */
class RuleRepository implements RuleRepositoryInterface
{
    /**
     * @var RuleResource
     */
    private RuleResource $resource;

    /**
     * @var RuleFactory
     */
    private RuleFactory $ruleFactory;

    /**
     * @var RuleCollectionFactory
     */
    private RuleCollectionFactory $collectionFactory;

    /**
     * @var RuleSearchResultsInterfaceFactory
     */
    private RuleSearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @param RuleResource $resource
     * @param RuleFactory $ruleFactory
     * @param RuleCollectionFactory $collectionFactory
     * @param RuleSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface|null $collectionProcessor
     */
    public function __construct(
        RuleResource $resource,
        RuleFactory $ruleFactory,
        RuleCollectionFactory $collectionFactory,
        RuleSearchResultsInterfaceFactory $searchResultsFactory,
        ?CollectionProcessorInterface $collectionProcessor = null
    ) {
        $this->resource = $resource;
        $this->ruleFactory = $ruleFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor
            ?: ObjectManager::getInstance()->get(CollectionProcessorInterface::class);
    }

    /**
     * Save rule
     *
     * @param RuleInterface $rule
     * @return RuleInterface
     * @throws CouldNotSaveException
     */
    public function save(RuleInterface $rule): RuleInterface
    {
        try {
            $this->resource->save($rule);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save rule: %1', $exception->getMessage()),
                $exception
            );
        }

        return $rule;
    }

    /**
     * Get rule by ID
     *
     * @param int $ruleId
     * @return RuleInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): RuleInterface
    {
        $rule = $this->ruleFactory->create();
        $this->resource->load($rule, $ruleId);
        if (!$rule->getId()) {
            throw new NoSuchEntityException(
                __('Rule with ID "%1" does not exist.', $ruleId)
            );
        }

        return $rule;
    }

    /**
     * Get rule by code
     *
     * @param string $codigo
     * @return RuleInterface
     * @throws NoSuchEntityException
     */
    public function getByCodigo(string $codigo): RuleInterface
    {
        $rule = $this->ruleFactory->create();
        $this->resource->load($rule, $codigo, RuleInterface::CODIGO);
        if (!$rule->getId()) {
            throw new NoSuchEntityException(
                __('Rule with code "%1" does not exist.', $codigo)
            );
        }

        return $rule;
    }

    /**
     * Get rule by code (alias for getByCodigo)
     *
     * @param string $code
     * @return RuleInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): RuleInterface
    {
        return $this->getByCodigo($code);
    }

    /**
     * Delete rule
     *
     * @param RuleInterface $rule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(RuleInterface $rule): bool
    {
        try {
            $this->resource->delete($rule);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete rule: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }

    /**
     * Delete rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $ruleId): bool
    {
        return $this->delete($this->getById($ruleId));
    }

    /**
     * Retrieve rules matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return RuleSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RuleSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        $this->collectionProcessor->process($searchCriteria, $collection);

        /** @var RuleSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
