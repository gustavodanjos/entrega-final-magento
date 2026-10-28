<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use Exception;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Model\Group;
use Nuvem\Atelie\Model\GroupFactory;
use Nuvem\Atelie\Model\GroupRepository;
use Nuvem\Atelie\Model\GroupSearchResultsFactory;
use Nuvem\Atelie\Model\ResourceModel\Group as GroupResource;
use Nuvem\Atelie\Model\ResourceModel\Group\Collection as GroupCollection;
use Nuvem\Atelie\Model\ResourceModel\Group\CollectionFactory as GroupCollectionFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GroupRepositoryTest extends TestCase
{
    /**
     * @var GroupResource|MockObject
     */
    private MockObject $resourceMock;

    /**
     * @var GroupFactory|MockObject
     */
    private MockObject $groupFactoryMock;

    /**
     * @var GroupCollectionFactory|MockObject
     */
    private MockObject $collectionFactoryMock;

    /**
     * @var GroupSearchResultsFactory|MockObject
     */
    private MockObject $searchResultsFactoryMock;

    /**
     * @var CollectionProcessorInterface|MockObject
     */
    private MockObject $collectionProcessorMock;

    /**
     * @var GroupRepository
     */
    private GroupRepository $repository;

    public static function setUpBeforeClass(): void
    {
        if (class_exists(\Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader::class)) {
            $generatorIo = new \Magento\Framework\Code\Generator\Io(
                new \Magento\Framework\Filesystem\Driver\File(),
                sys_get_temp_dir() . '/generated/code'
            );
            $generatedCodeAutoloader = new \Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader(
                [
                    new \Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesGenerator(),
                    new \Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesInterfaceGenerator(),
                    new \Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator(),
                    new \Magento\Framework\TestFramework\Unit\Autoloader\ProxyGenerator()
                ],
                $generatorIo
            );
            spl_autoload_register([$generatedCodeAutoloader, 'load']);
        }
    }

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(GroupResource::class);
        $this->groupFactoryMock = $this->createMock(GroupFactory::class);
        $this->collectionFactoryMock = $this->createMock(GroupCollectionFactory::class);
        $this->searchResultsFactoryMock = $this->createMock(GroupSearchResultsFactory::class);
        $this->collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);

        $this->repository = new GroupRepository(
            $this->resourceMock,
            $this->groupFactoryMock,
            $this->collectionFactoryMock,
            $this->searchResultsFactoryMock,
            $this->collectionProcessorMock
        );
    }

    public function testSave(): void
    {
        $groupMock = $this->createMock(Group::class);
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($groupMock);

        $result = $this->repository->save($groupMock);
        $this->assertSame($groupMock, $result);
    }

    public function testSaveThrowsCouldNotSaveException(): void
    {
        $groupMock = $this->createMock(Group::class);
        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($groupMock)
            ->willThrowException(new Exception('DB error'));

        $this->expectException(CouldNotSaveException::class);
        $this->expectExceptionMessage('Could not save group: DB error');

        $this->repository->save($groupMock);
    }

    public function testGetById(): void
    {
        $groupMock = $this->createMock(Group::class);
        $groupMock->method('getId')->willReturn(1);

        $this->groupFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($groupMock);

        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($groupMock, 1);

        $this->assertSame($groupMock, $this->repository->getById(1));
    }

    public function testGetByIdThrowsNoSuchEntityException(): void
    {
        $groupMock = $this->createMock(Group::class);
        $groupMock->method('getId')->willReturn(null);

        $this->groupFactoryMock->method('create')->willReturn($groupMock);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Group with ID "99" does not exist.');

        $this->repository->getById(99);
    }

    public function testGetByCodigo(): void
    {
        $groupMock = $this->createMock(Group::class);
        $groupMock->method('getId')->willReturn(1);

        $this->groupFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($groupMock);

        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($groupMock, 'cabedal', GroupInterface::CODIGO);

        $this->assertSame($groupMock, $this->repository->getByCodigo('cabedal'));
    }

    public function testGetByCodigoThrowsNoSuchEntityException(): void
    {
        $groupMock = $this->createMock(Group::class);
        $groupMock->method('getId')->willReturn(null);

        $this->groupFactoryMock->method('create')->willReturn($groupMock);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Group with code "nao-existe" does not exist.');

        $this->repository->getByCodigo('nao-existe');
    }

    public function testGetByCodeAlias(): void
    {
        $groupMock = $this->createMock(Group::class);
        $groupMock->method('getId')->willReturn(1);

        $this->groupFactoryMock->method('create')->willReturn($groupMock);
        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($groupMock, 'solado', GroupInterface::CODIGO);

        $this->assertSame($groupMock, $this->repository->getByCode('solado'));
    }

    public function testDelete(): void
    {
        $groupMock = $this->createMock(Group::class);
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($groupMock);

        $this->assertTrue($this->repository->delete($groupMock));
    }

    public function testDeleteThrowsCouldNotDeleteException(): void
    {
        $groupMock = $this->createMock(Group::class);
        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($groupMock)
            ->willThrowException(new Exception('Cannot delete'));

        $this->expectException(CouldNotDeleteException::class);
        $this->expectExceptionMessage('Could not delete group: Cannot delete');

        $this->repository->delete($groupMock);
    }

    public function testDeleteById(): void
    {
        $groupMock = $this->createMock(Group::class);
        $groupMock->method('getId')->willReturn(5);

        $this->groupFactoryMock->method('create')->willReturn($groupMock);
        $this->resourceMock->expects($this->once())->method('load')->with($groupMock, 5);
        $this->resourceMock->expects($this->once())->method('delete')->with($groupMock);

        $this->assertTrue($this->repository->deleteById(5));
    }

    public function testGetList(): void
    {
        $searchCriteriaMock = $this->createMock(SearchCriteriaInterface::class);
        $collectionMock = $this->createMock(GroupCollection::class);
        $searchResultsMock = $this->createMock(GroupSearchResultsInterface::class);
        $groupMock = $this->createMock(Group::class);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->collectionProcessorMock->expects($this->once())
            ->method('process')
            ->with($searchCriteriaMock, $collectionMock);

        $collectionMock->expects($this->once())
            ->method('getItems')
            ->willReturn([$groupMock]);
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
            ->with([$groupMock]);
        $searchResultsMock->expects($this->once())
            ->method('setTotalCount')
            ->with(1);

        $this->assertSame($searchResultsMock, $this->repository->getList($searchCriteriaMock));
    }

    public function testGetListReturnsEmptyPageWhenNoItemsFound(): void
    {
        $searchCriteriaMock = $this->createMock(SearchCriteriaInterface::class);
        $collectionMock = $this->createMock(GroupCollection::class);
        $searchResultsMock = $this->createMock(GroupSearchResultsInterface::class);

        $this->collectionFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($collectionMock);

        $this->collectionProcessorMock->expects($this->once())
            ->method('process')
            ->with($searchCriteriaMock, $collectionMock);

        $collectionMock->expects($this->once())
            ->method('getItems')
            ->willReturn([]);
        $collectionMock->expects($this->once())
            ->method('getSize')
            ->willReturn(0);

        $this->searchResultsFactoryMock->expects($this->once())
            ->method('create')
            ->willReturn($searchResultsMock);

        $searchResultsMock->expects($this->once())
            ->method('setSearchCriteria')
            ->with($searchCriteriaMock);
        $searchResultsMock->expects($this->once())
            ->method('setItems')
            ->with([]);
        $searchResultsMock->expects($this->once())
            ->method('setTotalCount')
            ->with(0);

        $result = $this->repository->getList($searchCriteriaMock);
        $this->assertSame($searchResultsMock, $result);
    }

    public function testGetByCodeAliasThrowsNoSuchEntityException(): void
    {
        $groupMock = $this->createMock(Group::class);
        $groupMock->method('getId')->willReturn(null);

        $this->groupFactoryMock->method('create')->willReturn($groupMock);
        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($groupMock, 'NON_EXISTENT', GroupInterface::CODIGO);

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Group with code "NON_EXISTENT" does not exist.');

        $this->repository->getByCode('NON_EXISTENT');
    }
}
