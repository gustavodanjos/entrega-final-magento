<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\ResourceModel\Rule as RuleResource;
use Nuvem\Atelie\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Nuvem\Atelie\Model\Rule;
use Nuvem\Atelie\Model\RuleFactory;
use Nuvem\Atelie\Model\RuleSearchResultsFactory;

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
     * @var RuleSearchResultsFactory
     */
    private RuleSearchResultsFactory $searchResultsFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @var CacheInterface|null
     */
    private ?CacheInterface $cache;

    /**
     * @param RuleResource $resource
     * @param RuleFactory $ruleFactory
     * @param RuleCollectionFactory $collectionFactory
     * @param RuleSearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param CacheInterface|null $cache
     */
    public function __construct(
        RuleResource $resource,
        RuleFactory $ruleFactory,
        RuleCollectionFactory $collectionFactory,
        RuleSearchResultsFactory $searchResultsFactory,
        CollectionProcessorInterface $collectionProcessor,
        ?CacheInterface $cache = null
    ) {
        $this->resource = $resource;
        $this->ruleFactory = $ruleFactory;
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->cache = $cache;
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
            if ($this->cache !== null) {
                $this->cache->clean([Rule::CACHE_TAG]);
            }
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
            if ($this->cache !== null) {
                $this->cache->clean([Rule::CACHE_TAG]);
            }
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
