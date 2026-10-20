<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;

/**
 * Interface GroupRepositoryInterface
 *
 * Repository for managing customization groups.
 *
 * @api
 */
interface GroupRepositoryInterface
{
    /**
     * Save group
     *
     * @param GroupInterface $group
     * @return GroupInterface
     * @throws CouldNotSaveException
     */
    public function save(GroupInterface $group): GroupInterface;

    /**
     * Get group by ID
     *
     * @param int $groupId
     * @return GroupInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $groupId): GroupInterface;

    /**
     * Get group by code
     *
     * @param string $codigo
     * @return GroupInterface
     * @throws NoSuchEntityException
     */
    public function getByCodigo(string $codigo): GroupInterface;

    /**
     * Get group by code (alias for getByCodigo)
     *
     * @param string $code
     * @return GroupInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): GroupInterface;

    /**
     * Delete group
     *
     * @param GroupInterface $group
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(GroupInterface $group): bool;

    /**
     * Delete group by ID
     *
     * @param int $groupId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $groupId): bool;

    /**
     * Retrieve groups matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return GroupSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): GroupSearchResultsInterface;
}
