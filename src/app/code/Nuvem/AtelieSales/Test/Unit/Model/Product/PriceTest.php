<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model\Product;

use InvalidArgumentException;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\Option;
use Magento\Catalog\Model\Product\Configuration\Item\OptionFactory;
use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Catalog\Model\Product\Type\Price as CorePriceModel;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\PriceCalculatorInterface;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Selection;
use Nuvem\AtelieSales\Model\Product\Price;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PriceTest extends TestCase
{
    private const CATALOG_BASE = 449.90;
    private const FULL_PRICE = 589.40;
    private const SPECIAL_BASE = 404.91;
    private const SPECIAL_TOTAL = 544.41;

    private ObjectManagerHelper $objectManagerHelper;

    private MockObject&PriceCalculatorInterface $priceCalculator;

    private MockObject&AtelieCatalogInterface $catalog;

    private MockObject&PriceCurrencyInterface $priceCurrency;

    protected function setUp(): void
    {
        $this->objectManagerHelper = new ObjectManagerHelper($this);
        $this->priceCalculator = $this->createMock(PriceCalculatorInterface::class);
        $this->catalog = $this->createMock(AtelieCatalogInterface::class);
        $this->priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $this->priceCurrency->method('round')->willReturnArgument(0);
    }

    public function testWithoutConfigurationReturnsTheCoreResolvedBasePrice(): void
    {
        $product = $this->createProduct();
        $price = $this->createPrice();
        $this->priceCalculator->expects($this->never())->method('calculate');
        $this->catalog->expects($this->never())->method('getBySku');

        $this->assertEqualsWithDelta(
            self::CATALOG_BASE,
            $price->getFinalPrice(null, $product),
            0.001
        );
    }

    public function testEmptyConfigurationOptionIsTreatedAsAbsent(): void
    {
        $product = $this->createProduct('   ');
        $price = $this->createPrice();
        $this->priceCalculator->expects($this->never())->method('calculate');

        $this->assertEqualsWithDelta(
            self::CATALOG_BASE,
            $price->getFinalPrice(null, $product),
            0.001
        );
    }

    public function testConfigurationChargesBasePlusAdditionsAndEngraving(): void
    {
        $product = $this->createProduct($this->configurationJson());
        $price = $this->createPrice();
        $this->catalog->method('getBySku')->with('NUVEM-RUNNER')
            ->willReturn(['preco_base' => self::CATALOG_BASE]);
        $this->priceCalculator->method('calculate')->willReturn(self::FULL_PRICE);

        $this->assertEqualsWithDelta(
            self::FULL_PRICE,
            $price->getFinalPrice(null, $product),
            0.001
        );
    }

    public function testSpecialPriceReducesOnlyTheBasePrice(): void
    {
        $product = $this->createProduct($this->configurationJson());
        $price = $this->createPrice(StubbedBasePrice::class);
        $price->stubbedBase = self::SPECIAL_BASE;
        $this->catalog->method('getBySku')->willReturn(['preco_base' => self::CATALOG_BASE]);
        $this->priceCalculator->method('calculate')->willReturn(self::FULL_PRICE);

        $this->assertEqualsWithDelta(
            self::SPECIAL_TOTAL,
            $price->getFinalPrice(null, $product),
            0.001
        );
    }

    public function testProductStoreScopeIsForwardedToTheCalculator(): void
    {
        $product = $this->createProduct($this->configurationJson(), 7);
        $price = $this->createPrice();
        $this->catalog->method('getBySku')->willReturn(['preco_base' => self::CATALOG_BASE]);
        $this->priceCalculator->expects($this->once())
            ->method('calculate')
            ->with(
                'NUVEM-RUNNER',
                $this->isInstanceOf(ConfigurationInterface::class),
                7
            )
            ->willReturn(self::FULL_PRICE);

        $price->getFinalPrice(null, $product);
    }

    public function testCorruptedConfigurationJsonFailsLoudly(): void
    {
        $product = $this->createProduct('{not contract json');
        $price = $this->createPrice();
        $this->priceCalculator->expects($this->never())->method('calculate');

        $this->expectException(InvalidArgumentException::class);

        $price->getFinalPrice(null, $product);
    }

    public function testCatalogBaseIsMemoizedPerSkuAcrossPrices(): void
    {
        $product = $this->createProduct($this->configurationJson());
        $price = $this->createPrice();
        $this->catalog->expects($this->once())
            ->method('getBySku')
            ->with('NUVEM-RUNNER')
            ->willReturn(['preco_base' => self::CATALOG_BASE]);
        $this->priceCalculator->method('calculate')->willReturn(self::FULL_PRICE);

        $this->assertEqualsWithDelta(self::FULL_PRICE, $price->getFinalPrice(null, $product), 0.001);
        $this->assertEqualsWithDelta(self::FULL_PRICE, $price->getFinalPrice(null, $product), 0.001);
    }

    private function createPrice(?string $className = null): Price
    {
        return $this->objectManagerHelper->getObject($className ?? Price::class, [
            'priceCalculator' => $this->priceCalculator,
            'catalog' => $this->catalog,
            'configurationSerializer' => new ConfigurationSerializer(),
            'priceCurrency' => $this->priceCurrency,
        ]);
    }

    private function createProduct(?string $configurationJson = null, int $storeId = 1): Product
    {
        $optionFactory = $this->createMock(OptionFactory::class);
        $optionFactory->method('create')->willReturn(new Option());

        $typeInstance = $this->createMock(AbstractType::class);
        $typeInstance->method('getSku')->willReturn('NUVEM-RUNNER');

        $corePriceModel = $this->getMockBuilder(CorePriceModel::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $catalogProductType = $this->createMock(ProductType::class);
        $catalogProductType->method('factory')->willReturn($typeInstance);
        $catalogProductType->method('priceFactory')->willReturn($corePriceModel);

        $product = $this->objectManagerHelper->getObject(Product::class, [
            'itemOptionFactory' => $optionFactory,
            'catalogProductType' => $catalogProductType,
        ]);
        $product->setData('price', self::CATALOG_BASE);
        $product->setData('store_id', $storeId);

        if ($configurationJson !== null) {
            $product->addCustomOption('atelie_configuracao', $configurationJson);
        }

        return $product;
    }

    private function configurationJson(): string
    {
        return (new ConfigurationSerializer())->serialize(new Configuration(
            38,
            'LUIZ',
            [
                new Selection('cabedal', 'CAB-TRICO-LAVANDA'),
                new Selection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
                new Selection('cadarco', 'CAD-ELASTICO-MENTA'),
                new Selection('solado', 'SOL-TRANSLUCIDO-GELO'),
            ]
        ));
    }
}

class StubbedBasePrice extends Price
{
    public float $stubbedBase = 449.90;

    protected function resolveProductBasePrice($qty, $product)
    {
        return $this->stubbedBase;
    }
}
