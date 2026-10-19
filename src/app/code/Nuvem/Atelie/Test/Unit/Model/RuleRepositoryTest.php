<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use Exception;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterfaceFactory;
use Nuvem\Atelie\Model\ResourceModel\Rule as RuleResource;
use Nuvem\Atelie\Model\ResourceModel\Rule\Collection as RuleCollection;
use Nuvem\Atelie\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;
use Nuvem\Atelie\Model\Rule;
use Nuvem\Atelie\Model\RuleFactory;
use Nuvem\Atelie\Model\RuleRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RuleRepositoryTest extends TestCase
{
    private RuleResource&MockObject $resourceMock;
    private RuleFactory&MockObject $ruleFactoryMock;
    private RuleCollectionFactory&MockObject $collectionFactoryMock;
    private RuleSearchResultsInterfaceFactory&MockObject $searchResultsFactoryMock;
    private CollectionProcessorInterface&MockObject $collectionProcessorMock;
    private RuleRepository $repository;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(RuleResource::class);
        $this->ruleFactoryMock = $this->createMock(RuleFactory::class);
        $this->collectionFactoryMock = $this->createMock(RuleCollectionFactory::class);
        $this->searchResultsFactoryMock = $this->createMock(RuleSearchResultsInterfaceFactory::class);
        $this->collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new RuleRepository(
            $this->resourceMock,
            $this->ruleFactoryMock,
            $this->collectionFactoryMock,
            $this->searchResultsFactoryMock,
            $this->collectionProcessorMock
        );
    }

    public function testSave(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($ruleMock);

        $result = $this->repository->save($ruleMock);
        $this->assertSame($ruleMock, $result);
    }

    public function testSaveThrowsCouldNotSaveException(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($ruleMock)
            ->willThrowException(new Exception('Write failed'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save rule: Write failed');

        $this->repository->save($ruleMock);
    }

    public function testGetById(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $ruleMock->method('getId')->willReturn(1);

        $this->ruleFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($ruleMock);

        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($ruleMock, 1);

        $this->assertSame($ruleMock, $this->repository->getById(1));
    }

    public function testGetByIdThrowsNoSuchEntityException(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $ruleMock->method('getId')->willReturn(null);

        $this->ruleFactoryMock->method('create')->willReturn($ruleMock);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Rule with ID "99" does not exist.');

        $this->repository->getById(99);
    }

    public function testGetByCodigo(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $ruleMock->method('getId')->willReturn(1);

        $this->ruleFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($ruleMock);

        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($ruleMock, 'R01', RuleInterface::CODIGO);

        $this->assertSame($ruleMock, $this->repository->getByCodigo('R01'));
    }

    public function testGetByCodigoThrowsNoSuchEntityException(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $ruleMock->method('getId')->willReturn(null);

        $this->ruleFactoryMock->method('create')->willReturn($ruleMock);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Rule with code "R99" does not exist.');

        $this->repository->getByCodigo('R99');
    }

    public function testGetByCodeAlias(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $ruleMock->method('getId')->willReturn(1);

        $this->ruleFactoryMock->method('create')->willReturn($ruleMock);
        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($ruleMock, 'R02', RuleInterface::CODIGO);

        $this->assertSame($ruleMock, $this->repository->getByCode('R02'));
    }

    public function testDelete(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($ruleMock);

        $this->assertTrue($this->repository->delete($ruleMock));
    }

    public function testDeleteThrowsCouldNotDeleteException(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($ruleMock)
            ->willThrowException(new Exception('Delete error'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete rule: Delete error');

        $this->repository->delete($ruleMock);
    }

    public function testDeleteById(): void
    {
        $ruleMock = $this->createMock(Rule::class);
        $ruleMock->method('getId')->willReturn(2);

        $this->ruleFactoryMock->method('create')->willReturn($ruleMock);
        $this->resourceMock->expects($this->once())->method('load')->with($ruleMock, 2);
        $this->resourceMock->expects($this->once())->method('delete')->with($ruleMock);

        $this->assertTrue($this->repository->deleteById(2));
    }

    public function testGetList(): void
    {
        $searchCriteriaMock = $this->createMock(SearchCriteriaInterface::class);
        $collectionMock = $this->createMock(RuleCollection::class);
        $searchResultsMock = $this->createMock(RuleSearchResultsInterface::class);
        $ruleMock = $this->createMock(Rule::class);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->collectionProcessorMock->expects($this->once())
            ->method('process')
            ->with($searchCriteriaMock, $collectionMock);

        $collectionMock->expects($this->once())
            ->method('getItems')
            ->willReturn([$ruleMock]);
        $collectionMock->expects($this->once())
            ->method('getSize')
            ->willReturn(1);

        $this->searchResultsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($searchResultsMock);

        $searchResultsMock->expects($this->once())
            ->method('setSearchCriteria')
            ->with($searchCriteriaMock);
        $searchResultsMock->expects($this->once())
            ->method('setItems')
            ->with([$ruleMock]);
        $searchResultsMock->expects($this->once())
            ->method('setTotalCount')
            ->with(1);

        $this->assertSame($searchResultsMock, $this->repository->getList($searchCriteriaMock));
    }
}
