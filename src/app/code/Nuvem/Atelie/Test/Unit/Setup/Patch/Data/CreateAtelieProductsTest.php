<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Setup\Patch\Data;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Setup\Patch\Data\CreateAtelieProductAttributes;
use Nuvem\Atelie\Setup\Patch\Data\CreateAtelieProducts;
use Nuvem\Atelie\Setup\Patch\Data\EnableProductAttributesForAtelie;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CreateAtelieProductsTest extends TestCase
{
    /**
     * @var ModuleDataSetupInterface|MockObject
     */
    private ModuleDataSetupInterface|MockObject $moduleDataSetupMock;

    /**
     * @var EavSetupFactory|MockObject
     */
    private EavSetupFactory|MockObject $eavSetupFactoryMock;

    /**
     * @var ProductFactory|MockObject
     */
    private ProductFactory|MockObject $productFactoryMock;

    /**
     * @var ProductRepositoryInterface|MockObject
     */
    private ProductRepositoryInterface|MockObject $productRepositoryMock;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private StoreManagerInterface|MockObject $storeManagerMock;

    /**
     * @var State|MockObject
     */
    private State|MockObject $appStateMock;

    /**
     * @var StockRegistryInterface|MockObject
     */
    private StockRegistryInterface|MockObject $stockRegistryMock;

    /**
     * @var ModuleReader|MockObject
     */
    private ModuleReader|MockObject $moduleReaderMock;

    /**
     * @var FileDriver|MockObject
     */
    private FileDriver|MockObject $fileDriverMock;

    /**
     * @var Csv|MockObject
     */
    private Csv|MockObject $csvReaderMock;

    /**
     * @var AdapterInterface|MockObject
     */
    private AdapterInterface|MockObject $connectionMock;

    /**
     * @var EavSetup|MockObject
     */
    private EavSetup|MockObject $eavSetupMock;

    /**
     * @var CreateAtelieProducts
     */
    private CreateAtelieProducts $patch;

    protected function setUp(): void
    {
        $this->moduleDataSetupMock = $this->createMock(ModuleDataSetupInterface::class);
        $this->eavSetupFactoryMock = $this->createMock(EavSetupFactory::class);
        $this->productFactoryMock = $this->createMock(ProductFactory::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->appStateMock = $this->createMock(State::class);
        $this->stockRegistryMock = $this->createMock(StockRegistryInterface::class);
        $this->moduleReaderMock = $this->createMock(ModuleReader::class);
        $this->fileDriverMock = $this->createMock(FileDriver::class);
        $this->csvReaderMock = $this->createMock(Csv::class);
        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->eavSetupMock = $this->createMock(EavSetup::class);

        $this->moduleDataSetupMock->method('getConnection')->willReturn($this->connectionMock);
        $this->moduleDataSetupMock->method('getTable')->willReturnArgument(0);

        $this->eavSetupFactoryMock->method('create')
            ->with(['setup' => $this->moduleDataSetupMock])
            ->willReturn($this->eavSetupMock);

        $this->patch = new CreateAtelieProducts(
            $this->moduleDataSetupMock,
            $this->eavSetupFactoryMock,
            $this->productFactoryMock,
            $this->productRepositoryMock,
            $this->storeManagerMock,
            $this->appStateMock,
            $this->stockRegistryMock,
            $this->moduleReaderMock,
            $this->fileDriverMock,
            $this->csvReaderMock
        );
    }

    public function testApplyDelegatesToEmulateAreaCode(): void
    {
        $this->appStateMock->expects($this->once())
            ->method('emulateAreaCode')
            ->with(Area::AREA_ADMINHTML, [$this->patch, 'executeProductCreation']);

        $result = $this->patch->apply();
        $this->assertSame($this->patch, $result);
    }

    public function testExecuteProductCreationCreatesProductsFromCsv(): void
    {
        $this->moduleDataSetupMock->expects($this->once())->method('startSetup');
        $this->moduleDataSetupMock->expects($this->once())->method('endSetup');

        $this->eavSetupMock->method('getAttributeSet')
            ->with(Product::ENTITY, 'Ateliê', 'attribute_set_id')
            ->willReturn(15);

        $websiteMock = $this->createMock(WebsiteInterface::class);
        $websiteMock->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getWebsites')->willReturn([$websiteMock]);

        $storeMock = $this->createMock(StoreInterface::class);
        $storeMock->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getDefaultStoreView')->willReturn($storeMock);

        $this->moduleReaderMock->method('getModuleDir')
            ->with('', 'Nuvem_Atelie')
            ->willReturn('/path/to/Nuvem/Atelie');

        $this->fileDriverMock->method('isExists')->willReturn(true);

        $csvData = [
            ['sku', 'nome', 'preco_base', 'tamanho_min', 'tamanho_max', 'permite_gravacao', 'prazo_producao_dias'],
            ['NUVEM-RUNNER', 'Nuvem Runner', '449.90', '34', '44', '1', '12'],
            ['NUVEM-MINI', 'Nuvem Mini', '329.90', '26', '33', '0', '10'],
        ];
        $this->csvReaderMock->method('getData')->willReturn($csvData);

        $selectMock = $this->createMock(Select::class);
        $selectMock->method('from')->willReturnSelf();
        $selectMock->method('where')->willReturnSelf();
        $this->connectionMock->method('select')->willReturn($selectMock);
        $this->connectionMock->method('fetchOne')->willReturn(null);

        $productRunnerMock = $this->createMock(Product::class);
        $productRunnerMock->method('getId')->willReturn(101);

        $productMiniMock = $this->createMock(Product::class);
        $productMiniMock->method('getId')->willReturn(102);

        $this->productRepositoryMock->method('get')
            ->willReturnCallback(function (string $sku) use ($productRunnerMock) {
                if ($sku === 'NUVEM-RUNNER') {
                    return $productRunnerMock;
                }
                throw new NoSuchEntityException(__('Not found'));
            });

        $this->productFactoryMock->method('create')->willReturn($productMiniMock);

        $productRunnerMock->expects($this->once())->method('setSku')->with('NUVEM-RUNNER');
        $productRunnerMock->expects($this->once())->method('setName')->with('Nuvem Runner');
        $productRunnerMock->expects($this->once())->method('setPrice')->with(449.90);
        $productRunnerMock->expects($this->once())->method('setTypeId')->with(Atelie::TYPE_CODE);
        $productRunnerMock->expects($this->once())->method('setAttributeSetId')->with(15);
        $productRunnerMock->expects($this->once())->method('setStatus')->with(Status::STATUS_ENABLED);
        $productRunnerMock->expects($this->once())->method('setVisibility')->with(Visibility::VISIBILITY_BOTH);

        $productMiniMock->expects($this->once())->method('setSku')->with('NUVEM-MINI');
        $productMiniMock->expects($this->once())->method('setName')->with('Nuvem Mini');
        $productMiniMock->expects($this->once())->method('setPrice')->with(329.90);
        $productMiniMock->expects($this->once())->method('setTypeId')->with(Atelie::TYPE_CODE);
        $productMiniMock->expects($this->once())->method('setAttributeSetId')->with(15);
        $productMiniMock->expects($this->once())->method('setStatus')->with(Status::STATUS_ENABLED);
        $productMiniMock->expects($this->once())->method('setVisibility')->with(Visibility::VISIBILITY_BOTH);

        $this->productRepositoryMock->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function ($product) {
                return $product;
            });

        $stockItemMock = $this->createMock(StockItemInterface::class);
        $stockItemMock->expects($this->exactly(2))->method('setIsInStock')->with(true);
        $stockItemMock->expects($this->exactly(2))->method('setManageStock')->with(false);
        $stockItemMock->expects($this->exactly(2))->method('setUseConfigManageStock')->with(false);
        $this->stockRegistryMock->method('getStockItemBySku')->willReturn($stockItemMock);
        $this->stockRegistryMock->expects($this->exactly(2))->method('updateStockItemBySku');

        $this->connectionMock->expects($this->exactly(2))
            ->method('update')
            ->with('catalog_product_entity', ['attribute_set_id' => 15], $this->anything());

        $this->connectionMock->expects($this->exactly(2))
            ->method('insert')
            ->with(
                'url_rewrite',
                $this->callback(function (array $data): bool {
                    return in_array($data['request_path'], ['nuvem-runner.html', 'nuvem-mini.html'], true);
                })
            );

        $this->patch->executeProductCreation();
    }

    public function testGetDependenciesReturnsExpectedPatches(): void
    {
        $dependencies = CreateAtelieProducts::getDependencies();
        $this->assertSame(
            [
                CreateAtelieProductAttributes::class,
                EnableProductAttributesForAtelie::class,
            ],
            $dependencies
        );
    }

    public function testGetAliasesReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->patch->getAliases());
    }
}
