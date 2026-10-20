<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Product\Type\Atelie;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Checks component-based availability for Atelie customizable products.
 */
class AvailabilityChecker
{
    /**
     * @param GroupRepositoryInterface $groupRepository
     * @param ComponentRepositoryInterface $componentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Check if all active groups have at least one active component available in stock.
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        try {
            $groupCriteria = $this->searchCriteriaBuilder->addFilter('ativo', 1)->create();
            $groups = $this->groupRepository->getList($groupCriteria)->getItems();

            if (empty($groups)) {
                return false;
            }

            $compCriteria = $this->searchCriteriaBuilder->addFilter('ativo', 1)->create();
            $components = $this->componentRepository->getList($compCriteria)->getItems();

            $availableGroupIds = [];
            foreach ($components as $component) {
                if ($component->getEstoque() > 0) {
                    $availableGroupIds[(int) $component->getGrupoId()] = true;
                }
            }

            foreach ($groups as $group) {
                $groupId = (int) $group->getGrupoId();
                if (!isset($availableGroupIds[$groupId])) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error checking atelie groups availability: ' . $e->getMessage());
            return false;
        }
    }
}
