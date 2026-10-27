<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Block;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use Nuvem\AtelieVitrine\Block\Gallery;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\Collection;
use Nuvem\AtelieVitrine\Model\ResourceModel\SavedCreation\CollectionFactory;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use PHPUnit\Framework\TestCase;

class GalleryTest extends TestCase
{
    private static GeneratedClassesAutoloader $factoryAutoloader;

    public static function setUpBeforeClass(): void
    {
        self::$factoryAutoloader = new GeneratedClassesAutoloader(
            [new FactoryGenerator()],
            new Io(new File(), sys_get_temp_dir() . '/nuvem-vitrine-unit-generated')
        );
        spl_autoload_register([self::$factoryAutoloader, 'load']);
    }

    public static function tearDownAfterClass(): void
    {
        spl_autoload_unregister([self::$factoryAutoloader, 'load']);
    }

    public function testPopularCollectionFiltersPublicCreationsAndUsesStablePagination(): void
    {
        $collection = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $collection->expects(self::once())->method('addFieldToFilter')->with('main_table.publica', 1)->willReturnSelf();
        $orders = [];
        $collection->expects(self::exactly(3))->method('setOrder')->willReturnCallback(
            function ($field, $direction) use (&$orders, $collection) {
                $orders[] = [$field, $direction];
                return $collection;
            }
        );
        $collection->expects(self::once())->method('setPageSize')->with(12)->willReturnSelf();
        $collection->expects(self::once())->method('setCurPage')->with(2)->willReturnSelf();
        $block = $this->createGallery($collection, ['order' => 'popular', 'p' => '2']);
        self::assertSame($collection, $block->getCreations());
        self::assertSame($collection, $block->getCreations());
        self::assertSame([['curtidas', 'DESC'], ['created_at', 'DESC'], ['main_table.criacao_id', 'DESC']], $orders);
    }

    public function testInvalidParametersUseRecentFirstPageAndEmptyGalleryKeepsSharedIdentity(): void
    {
        $collection = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $fields = [];
        $collection->method('setOrder')->willReturnCallback(function ($field) use (&$fields, $collection) {
            $fields[] = $field;
            return $collection;
        });
        $collection->method('setPageSize')->willReturnSelf();
        $collection->expects(self::once())->method('setCurPage')->with(1)->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $block = $this->createGallery($collection, ['order' => ['popular'], 'p' => '-3']);
        self::assertSame('recent', $block->getOrder());
        self::assertSame([SavedCreation::CACHE_TAG], $block->getIdentities());
        self::assertSame(['created_at', 'main_table.criacao_id'], $fields);
    }

    public function testVisibleCreationIdentitiesAreIncludedWithoutDuplicates(): void
    {
        $creation = $this->getMockBuilder(SavedCreation::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();
        $creation->setId(42);
        $collection = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$creation]));
        self::assertSame(
            [SavedCreation::CACHE_TAG, SavedCreation::CACHE_TAG . '_42'],
            $this->createGallery($collection, [])->getIdentities()
        );
    }

    public function testPreviewReceivesStoredConfigurationUnchanged(): void
    {
        $collection = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $block = $this->createGallery($collection, []);
        $preview = $this->createMock(PreviewInterface::class);
        $configuration = '{"tamanho":38,"gravacao":null,"selecoes":[]}';
        $preview->expects(self::once())->method('getUrl')->with($configuration)->willReturn('/signed-preview');
        (new \ReflectionProperty(Gallery::class, 'preview'))->setValue($block, $preview);
        $creation = $this->getMockBuilder(SavedCreation::class)->disableOriginalConstructor()
            ->onlyMethods([])->getMock();
        $creation->setData('configuracao', $configuration);
        self::assertSame('/signed-preview', $block->getPreviewUrl($creation));
    }

    public function testExistingSeedCreationsAreNeverUpdatedOrRepublished(): void
    {
        $factory = $this->createMock(\Nuvem\AtelieVitrine\Model\SavedCreationFactory::class);
        $factory->expects(self::never())->method('create');
        $repository = $this->getMockBuilder(\Nuvem\AtelieVitrine\Model\SavedCreationRepository::class)
            ->disableOriginalConstructor()->getMock();
        $creation = $this->getMockBuilder(SavedCreation::class)->disableOriginalConstructor()->getMock();
        $repository->expects(self::exactly(16))->method('getByHash')->willReturn($creation);
        $repository->expects(self::never())->method('save');
        $serializer = $this->createMock(\Nuvem\Atelie\Model\ConfigurationSerializer::class);
        $serializer->expects(self::never())->method('unserialize');
        $validator = $this->createMock(\Nuvem\Atelie\Api\ConfigurationValidatorInterface::class);
        $validator->expects(self::never())->method('validate');
        $patch = new \Nuvem\AtelieVitrine\Setup\Patch\Data\SeedPublicGallery(
            $factory,
            $repository,
            $serializer,
            $validator
        );
        self::assertSame($patch, $patch->apply());
    }

    private function createGallery(Collection $collection, array $parameters): Gallery
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);
        $request = $this->createMock(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn ($name, $default = null) => $parameters[$name] ?? $default
        );
        $block = $this->getMockBuilder(Gallery::class)->disableOriginalConstructor()
            ->onlyMethods(['getRequest'])->getMock();
        $block->method('getRequest')->willReturn($request);
        (new \ReflectionProperty(Gallery::class, 'collectionFactory'))->setValue($block, $factory);
        return $block;
    }
}
