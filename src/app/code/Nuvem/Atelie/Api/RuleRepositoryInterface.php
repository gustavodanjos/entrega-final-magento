<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;

/**
 * Interface RuleRepositoryInterface
 *
 * Repository for managing customization rules.
 *
 * @api
 */
interface RuleRepositoryInterface
{
    /**
     * Save rule
     *
     * @param RuleInterface $rule
     * @return RuleInterface
     * @throws CouldNotSaveException
     */
    public function save(RuleInterface $rule): RuleInterface;

    /**
     * Get rule by ID
     *
     * @param int $ruleId
     * @return RuleInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): RuleInterface;

    /**
     * Get rule by code
     *
     * @param string $codigo
     * @return RuleInterface
     * @throws NoSuchEntityException
     */
    public function getByCodigo(string $codigo): RuleInterface;

    /**
     * Get rule by code (alias for getByCodigo)
     *
     * @param string $code
     * @return RuleInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): RuleInterface;

    /**
     * Delete rule
     *
     * @param RuleInterface $rule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(RuleInterface $rule): bool;

    /**
     * Delete rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $ruleId): bool;

    /**
     * Retrieve rules matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return RuleSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RuleSearchResultsInterface;
}
