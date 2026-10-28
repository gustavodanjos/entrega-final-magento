<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;

/**
 * Interface ComponentRepositoryInterface
 *
 * Repository for managing customization components.
 *
 * @api
 */
interface ComponentRepositoryInterface
{
    /**
     * Save component
     *
     * @param ComponentInterface $component
     * @return ComponentInterface
     * @throws CouldNotSaveException
     */
    public function save(ComponentInterface $component): ComponentInterface;

    /**
     * Get component by ID
     *
     * @param int $componentId
     * @return ComponentInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $componentId): ComponentInterface;

    /**
     * Get component by code
     *
     * @param string $codigo
     * @return ComponentInterface
     * @throws NoSuchEntityException
     */
    public function getByCodigo(string $codigo): ComponentInterface;

    /**
     * Get component by code (alias for getByCodigo)
     *
     * @param string $code
     * @return ComponentInterface
     * @throws NoSuchEntityException
     */
    public function getByCode(string $code): ComponentInterface;

    /**
     * Delete component
     *
     * @param ComponentInterface $component
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ComponentInterface $component): bool;

    /**
     * Delete component by ID
     *
     * @param int $componentId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $componentId): bool;

    /**
     * Retrieve components matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return ComponentSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ComponentSearchResultsInterface;
}
