<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
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
     * @var CategoryRepositoryInterface|MockObject
     */
    private CategoryRepositoryInterface|MockObject $categoryRepositoryMock;

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
     * @var ProductUrlRewriteGenerator|MockObject
     */
    private ProductUrlRewriteGenerator|MockObject $productUrlRewriteGeneratorMock;

    /**
     * @var UrlPersistInterface|MockObject
     */
    private UrlPersistInterface|MockObject $urlPersistMock;

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
        $this->categoryRepositoryMock = $this->createMock(CategoryRepositoryInterface::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->appStateMock = $this->createMock(State::class);
        $this->stockRegistryMock = $this->createMock(StockRegistryInterface::class);
        $this->moduleReaderMock = $this->createMock(ModuleReader::class);
        $this->fileDriverMock = $this->createMock(FileDriver::class);
        $this->csvReaderMock = $this->createMock(Csv::class);
        $this->productUrlRewriteGeneratorMock = $this->createMock(ProductUrlRewriteGenerator::class);
        $this->urlPersistMock = $this->createMock(UrlPersistInterface::class);
        $this->eavSetupMock = $this->createMock(EavSetup::class);

        $this->eavSetupFactoryMock->method('create')
            ->with(['setup' => $this->moduleDataSetupMock])
            ->willReturn($this->eavSetupMock);

        $this->patch = new CreateAtelieProducts(
            $this->moduleDataSetupMock,
            $this->eavSetupFactoryMock,
            $this->productFactoryMock,
            $this->productRepositoryMock,
            $this->categoryRepositoryMock,
            $this->storeManagerMock,
            $this->appStateMock,
            $this->stockRegistryMock,
            $this->moduleReaderMock,
            $this->fileDriverMock,
            $this->csvReaderMock,
            $this->productUrlRewriteGeneratorMock,
            $this->urlPersistMock
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

        $categoryMock = $this->createMock(CategoryInterface::class);
        $this->categoryRepositoryMock->method('get')->with(38)->willReturn($categoryMock);

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

        $rewriteMock = $this->createMock(UrlRewrite::class);
        $this->productUrlRewriteGeneratorMock->expects($this->exactly(2))
            ->method('generate')
            ->willReturn([$rewriteMock]);

        $this->urlPersistMock->expects($this->exactly(2))
            ->method('replace')
            ->with([$rewriteMock]);

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
