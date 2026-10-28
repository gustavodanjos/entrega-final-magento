<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Product\Type\Atelie;

use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie\AvailabilityChecker;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AvailabilityCheckerTest extends TestCase
{
    private GroupRepositoryInterface|MockObject $groupRepoMock;
    private ComponentRepositoryInterface|MockObject $compRepoMock;
    private SearchCriteriaBuilder|MockObject $criteriaBuilderMock;
    private LoggerInterface|MockObject $loggerMock;
    private AvailabilityChecker $checker;

    protected function setUp(): void
    {
        $this->groupRepoMock = $this->createMock(GroupRepositoryInterface::class);
        $this->compRepoMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->criteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->checker = new AvailabilityChecker(
            $this->groupRepoMock,
            $this->compRepoMock,
            $this->criteriaBuilderMock,
            $this->loggerMock
        );
    }

    public function testReturnsFalseWhenNoActiveGroups(): void
    {
        $criteria = $this->createMock(SearchCriteria::class);
        $this->criteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->criteriaBuilderMock->method('create')->willReturn($criteria);

        $searchResult = $this->createMock(GroupSearchResultsInterface::class);
        $searchResult->method('getItems')->willReturn([]);
        $this->groupRepoMock->method('getList')->willReturn($searchResult);

        $this->assertFalse($this->checker->isAvailable());
    }

    public function testReturnsFalseWhenGroupHasNoInStockComponent(): void
    {
        $criteria = $this->createMock(SearchCriteria::class);
        $this->criteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->criteriaBuilderMock->method('create')->willReturn($criteria);

        $group1 = $this->createMock(GroupInterface::class);
        $group1->method('getGrupoId')->willReturn(1);
        $group2 = $this->createMock(GroupInterface::class);
        $group2->method('getGrupoId')->willReturn(2);

        $groupResult = $this->createMock(GroupSearchResultsInterface::class);
        $groupResult->method('getItems')->willReturn([$group1, $group2]);
        $this->groupRepoMock->method('getList')->willReturn($groupResult);

        $comp1 = $this->createMock(ComponentInterface::class);
        $comp1->method('getGrupoId')->willReturn(1);
        $comp1->method('getEstoque')->willReturn(5);

        $comp2 = $this->createMock(ComponentInterface::class);
        $comp2->method('getGrupoId')->willReturn(2);
        $comp2->method('getEstoque')->willReturn(0);

        $compResult = $this->createMock(ComponentSearchResultsInterface::class);
        $compResult->method('getItems')->willReturn([$comp1, $comp2]);
        $this->compRepoMock->method('getList')->willReturn($compResult);

        $this->assertFalse($this->checker->isAvailable());
    }

    public function testReturnsTrueWhenAllGroupsHaveStock(): void
    {
        $criteria = $this->createMock(SearchCriteria::class);
        $this->criteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->criteriaBuilderMock->method('create')->willReturn($criteria);

        $group1 = $this->createMock(GroupInterface::class);
        $group1->method('getGrupoId')->willReturn(1);
        $groupResult = $this->createMock(GroupSearchResultsInterface::class);
        $groupResult->method('getItems')->willReturn([$group1]);
        $this->groupRepoMock->method('getList')->willReturn($groupResult);

        $comp1 = $this->createMock(ComponentInterface::class);
        $comp1->method('getGrupoId')->willReturn(1);
        $comp1->method('getEstoque')->willReturn(10);
        $compResult = $this->createMock(ComponentSearchResultsInterface::class);
        $compResult->method('getItems')->willReturn([$comp1]);
        $this->compRepoMock->method('getList')->willReturn($compResult);

        $this->assertTrue($this->checker->isAvailable());
    }

    public function testReturnsFalseOnException(): void
    {
        $this->criteriaBuilderMock->method('addFilter')->willThrowException(new \Exception('DB error'));
        $this->loggerMock->expects($this->once())->method('error');

        $this->assertFalse($this->checker->isAvailable());
    }
}
