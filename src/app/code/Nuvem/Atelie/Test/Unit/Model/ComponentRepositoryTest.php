<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use Exception;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterfaceFactory;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\ComponentFactory;
use Nuvem\Atelie\Model\ComponentRepository;
use Nuvem\Atelie\Model\ResourceModel\Component as ComponentResource;
use Nuvem\Atelie\Model\ResourceModel\Component\Collection as ComponentCollection;
use Nuvem\Atelie\Model\ResourceModel\Component\CollectionFactory as ComponentCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ComponentRepositoryTest extends TestCase
{
    private ComponentResource&MockObject $resourceMock;
    private ComponentFactory&MockObject $componentFactoryMock;
    private ComponentCollectionFactory&MockObject $collectionFactoryMock;
    private ComponentSearchResultsInterfaceFactory&MockObject $searchResultsFactoryMock;
    private CollectionProcessorInterface&MockObject $collectionProcessorMock;
    private ComponentRepository $repository;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(ComponentResource::class);
        $this->componentFactoryMock = $this->createMock(ComponentFactory::class);
        $this->collectionFactoryMock = $this->createMock(ComponentCollectionFactory::class);
        $this->searchResultsFactoryMock = $this->createMock(ComponentSearchResultsInterfaceFactory::class);
        $this->collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new ComponentRepository(
            $this->resourceMock,
            $this->componentFactoryMock,
            $this->collectionFactoryMock,
            $this->searchResultsFactoryMock,
            $this->collectionProcessorMock
        );
    }

    public function testSave(): void
    {
        $componentMock = $this->createMock(Component::class);
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($componentMock);

        $result = $this->repository->save($componentMock);
        $this->assertSame($componentMock, $result);
    }

    public function testSaveThrowsCouldNotSaveException(): void
    {
        $componentMock = $this->createMock(Component::class);
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($componentMock)
            ->willThrowException(new Exception('DB write error'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save component: DB write error');

        $this->repository->save($componentMock);
    }

    public function testGetById(): void
    {
        $componentMock = $this->createMock(Component::class);
        $componentMock->method('getId')->willReturn(10);

        $this->componentFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($componentMock);

        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($componentMock, 10);

        $this->assertSame($componentMock, $this->repository->getById(10));
    }

    public function testGetByIdThrowsNoSuchEntityException(): void
    {
        $componentMock = $this->createMock(Component::class);
        $componentMock->method('getId')->willReturn(null);

        $this->componentFactoryMock->method('create')->willReturn($componentMock);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Component with ID "42" does not exist.');

        $this->repository->getById(42);
    }

    public function testGetByCodigo(): void
    {
        $componentMock = $this->createMock(Component::class);
        $componentMock->method('getId')->willReturn(1);

        $this->componentFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($componentMock);

        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($componentMock, 'CAB-TELA-MENTA', ComponentInterface::CODIGO);

        $this->assertSame($componentMock, $this->repository->getByCodigo('CAB-TELA-MENTA'));
    }

    public function testGetByCodigoThrowsNoSuchEntityException(): void
    {
        $componentMock = $this->createMock(Component::class);
        $componentMock->method('getId')->willReturn(null);

        $this->componentFactoryMock->method('create')->willReturn($componentMock);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Component with code "CAB-INEXISTENTE" does not exist.');

        $this->repository->getByCodigo('CAB-INEXISTENTE');
    }

    public function testGetByCodeAlias(): void
    {
        $componentMock = $this->createMock(Component::class);
        $componentMock->method('getId')->willReturn(1);

        $this->componentFactoryMock->method('create')->willReturn($componentMock);
        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($componentMock, 'SOL-LUA-HOLO', ComponentInterface::CODIGO);

        $this->assertSame($componentMock, $this->repository->getByCode('SOL-LUA-HOLO'));
    }

    public function testDelete(): void
    {
        $componentMock = $this->createMock(Component::class);
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($componentMock);

        $this->assertTrue($this->repository->delete($componentMock));
    }

    public function testDeleteThrowsCouldNotDeleteException(): void
    {
        $componentMock = $this->createMock(Component::class);
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($componentMock)
            ->willThrowException(new Exception('Foreign key violation'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete component: Foreign key violation');

        $this->repository->delete($componentMock);
    }

    public function testDeleteById(): void
    {
        $componentMock = $this->createMock(Component::class);
        $componentMock->method('getId')->willReturn(3);

        $this->componentFactoryMock->method('create')->willReturn($componentMock);
        $this->resourceMock->expects($this->once())->method('load')->with($componentMock, 3);
        $this->resourceMock->expects($this->once())->method('delete')->with($componentMock);

        $this->assertTrue($this->repository->deleteById(3));
    }

    public function testGetList(): void
    {
        $searchCriteriaMock = $this->createMock(SearchCriteriaInterface::class);
        $collectionMock = $this->createMock(ComponentCollection::class);
        $searchResultsMock = $this->createMock(ComponentSearchResultsInterface::class);
        $componentMock = $this->createMock(Component::class);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->collectionProcessorMock->expects($this->once())
            ->method('process')
            ->with($searchCriteriaMock, $collectionMock);

        $collectionMock->expects($this->once())
            ->method('getItems')
            ->willReturn([$componentMock]);
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
            ->with([$componentMock]);
        $searchResultsMock->expects($this->once())
            ->method('setTotalCount')
            ->with(1);

        $this->assertSame($searchResultsMock, $this->repository->getList($searchCriteriaMock));
    }
}
