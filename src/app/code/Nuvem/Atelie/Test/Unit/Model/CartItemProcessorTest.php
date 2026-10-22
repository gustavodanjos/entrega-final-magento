<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\ProductOptionExtensionFactory;
use Magento\Quote\Api\Data\ProductOptionExtensionInterface;
use Magento\Quote\Api\Data\ProductOptionInterface;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option as QuoteItemOption;
use Magento\Quote\Model\Quote\ProductOption;
use Magento\Quote\Model\Quote\ProductOptionFactory;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Model\CartItemProcessor;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Model\Product\Type\Atelie\BuyRequestReader;
use Nuvem\Atelie\Model\Selection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CartItemProcessor.
 */
class CartItemProcessorTest extends TestCase
{
    /**
     * @var DataObjectFactory|MockObject
     */
    private DataObjectFactory|MockObject $objectFactoryMock;

    /**
     * @var ProductOptionFactory|MockObject
     */
    private ProductOptionFactory|MockObject $productOptionFactoryMock;

    /**
     * @var ProductOptionExtensionFactory|MockObject
     */
    private ProductOptionExtensionFactory|MockObject $extensionFactoryMock;

    /**
     * @var ConfigurationSerializer
     */
    private ConfigurationSerializer $serializer;

    /**
     * @var BuyRequestReader
     */
    private BuyRequestReader $buyRequestReader;

    /**
     * @var CartItemProcessor
     */
    private CartItemProcessor $processor;

    /**
     * @inheritdoc
     */
    public static function setUpBeforeClass(): void
    {
        if (!interface_exists(ProductOptionExtensionInterface::class, false)) {
            require_once __DIR__ . '/Fixtures/ProductOptionExtensionInterface.php';
        }
    }

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        $this->objectFactoryMock = $this->createMock(DataObjectFactory::class);
        $this->productOptionFactoryMock = $this->createMock(ProductOptionFactory::class);
        $this->extensionFactoryMock = $this->createMock(ProductOptionExtensionFactory::class);
        $this->serializer = new ConfigurationSerializer();
        $this->buyRequestReader = new BuyRequestReader();

        $this->processor = new CartItemProcessor(
            $this->objectFactoryMock,
            $this->productOptionFactoryMock,
            $this->extensionFactoryMock,
            $this->serializer,
            $this->buyRequestReader
        );
    }

    public function testConvertToBuyRequestReturnsNullWhenNoProductOption(): void
    {
        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('getProductOption')->willReturn(null);

        $this->assertNull($this->processor->convertToBuyRequest($cartItem));
    }

    public function testConvertToBuyRequestReturnsNullWhenNoExtensionAttributes(): void
    {
        $cartItem = $this->createMock(CartItemInterface::class);
        $productOption = $this->createMock(ProductOptionInterface::class);
        $productOption->method('getExtensionAttributes')->willReturn(null);
        $cartItem->method('getProductOption')->willReturn($productOption);

        $this->assertNull($this->processor->convertToBuyRequest($cartItem));
    }

    public function testConvertToBuyRequestReturnsNullWhenExtensionHasNoAtelieConfiguracao(): void
    {
        $cartItem = $this->createMock(CartItemInterface::class);
        $productOption = $this->createMock(ProductOptionInterface::class);
        $extension = $this->createMock(ProductOptionExtensionInterface::class);
        $extension->method('getAtelieConfiguracao')->willReturn(null);

        $productOption->method('getExtensionAttributes')->willReturn($extension);
        $cartItem->method('getProductOption')->willReturn($productOption);

        $this->assertNull($this->processor->convertToBuyRequest($cartItem));
    }

    public function testConvertToBuyRequestSuccess(): void
    {
        $cartItem = $this->createMock(CartItemInterface::class);
        $productOption = $this->createMock(ProductOptionInterface::class);

        $config = new Configuration(38, 'LUIZ', [
            new Selection('cabedal', 'CAB-TRICO-LAVANDA'),
            new Selection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ]);

        $extension = $this->createMock(ProductOptionExtensionInterface::class);
        $extension->method('getAtelieConfiguracao')->willReturn($config);

        $productOption->method('getExtensionAttributes')->willReturn($extension);
        $cartItem->method('getProductOption')->willReturn($productOption);

        $dataObject = new DataObject();
        $this->objectFactoryMock->expects($this->once())
            ->method('create')
            ->with([
                'atelie' => [
                    'tamanho' => 38,
                    'gravacao' => 'LUIZ',
                    'selecoes' => [
                        ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                        ['grupo' => 'solado', 'componente' => 'SOL-TRANSLUCIDO-GELO'],
                    ],
                ],
            ])
            ->willReturn($dataObject);

        $result = $this->processor->convertToBuyRequest($cartItem);
        $this->assertSame($dataObject, $result);
    }

    public function testProcessOptionsReturnsSameItemWhenNotAtelieType(): void
    {
        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('getProductType')->willReturn('simple');

        $result = $this->processor->processOptions($cartItem);
        $this->assertSame($cartItem, $result);
    }

    public function testProcessOptionsReturnsSameItemWhenNoConfigurationResolved(): void
    {
        $cartItem = $this->createMock(CartItemInterface::class);
        $cartItem->method('getProductType')->willReturn(Atelie::TYPE_CODE);
        $cartItem->method('getProductOption')->willReturn(null);

        $result = $this->processor->processOptions($cartItem);
        $this->assertSame($cartItem, $result);
    }

    public function testProcessOptionsResolvesFromQuoteItemCustomOption(): void
    {
        $cartItem = $this->createMock(Item::class);
        $cartItem->method('getProductType')->willReturn(Atelie::TYPE_CODE);
        $cartItem->method('getProductOption')->willReturn(null);

        $json = $this->serializer->serialize(new Configuration(38, 'LUIZ', [
            new Selection('cabedal', 'CAB-TRICO-LAVANDA'),
            new Selection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ]));

        $quoteOption = $this->createMock(QuoteItemOption::class);
        $quoteOption->method('getValue')->willReturn($json);
        $cartItem->method('getOptionByCode')->with(Atelie::CUSTOM_OPTION_CONFIGURACAO)->willReturn($quoteOption);

        $productOptionMock = $this->createMock(ProductOption::class);
        $this->productOptionFactoryMock->method('create')->willReturn($productOptionMock);

        $extensionMock = $this->createMock(ProductOptionExtensionInterface::class);
        $this->extensionFactoryMock->method('create')->willReturn($extensionMock);

        $extensionMock->expects($this->once())
            ->method('setAtelieConfiguracao')
            ->with($this->callback(function (ConfigurationInterface $cfg) {
                return $cfg->getSize() === 38
                    && $cfg->getEngraving() === 'LUIZ'
                    && count($cfg->getSelections()) === 2;
            }));

        $productOptionMock->expects($this->once())
            ->method('setExtensionAttributes')
            ->with($extensionMock);

        $cartItem->expects($this->once())
            ->method('setProductOption')
            ->with($productOptionMock);

        $result = $this->processor->processOptions($cartItem);
        $this->assertSame($cartItem, $result);
    }

    public function testProcessOptionsResolvesFromProductCustomOption(): void
    {
        $cartItem = $this->createMock(Item::class);
        $cartItem->method('getProductType')->willReturn(Atelie::TYPE_CODE);
        $cartItem->method('getProductOption')->willReturn(null);
        $cartItem->method('getOptionByCode')->with(Atelie::CUSTOM_OPTION_CONFIGURACAO)->willReturn(null);

        $json = $this->serializer->serialize(new Configuration(40, null, [
            new Selection('cabedal', 'CAB-TRICO-PESSEGO'),
        ]));

        $productMock = $this->createMock(Product::class);
        $prodCustomOption = new DataObject(['value' => $json]);
        $productMock->method('getCustomOption')
            ->with(Atelie::CUSTOM_OPTION_CONFIGURACAO)
            ->willReturn($prodCustomOption);

        $cartItem->method('getProduct')->willReturn($productMock);

        $productOptionMock = $this->createMock(ProductOption::class);
        $this->productOptionFactoryMock->method('create')->willReturn($productOptionMock);

        $extensionMock = $this->createMock(ProductOptionExtensionInterface::class);
        $this->extensionFactoryMock->method('create')->willReturn($extensionMock);

        $extensionMock->expects($this->once())
            ->method('setAtelieConfiguracao')
            ->with($this->callback(function (ConfigurationInterface $cfg) {
                return $cfg->getSize() === 40 && $cfg->getEngraving() === null;
            }));

        $cartItem->expects($this->once())
            ->method('setProductOption')
            ->with($productOptionMock);

        $result = $this->processor->processOptions($cartItem);
        $this->assertSame($cartItem, $result);
    }

    public function testProcessOptionsResolvesFromBuyRequest(): void
    {
        $cartItem = $this->createMock(Item::class);
        $cartItem->method('getProductType')->willReturn(Atelie::TYPE_CODE);
        $cartItem->method('getProductOption')->willReturn(null);
        $cartItem->method('getOptionByCode')->with(Atelie::CUSTOM_OPTION_CONFIGURACAO)->willReturn(null);
        $cartItem->method('getProduct')->willReturn(null);

        $buyRequest = new DataObject([
            'atelie' => [
                'tamanho' => 42,
                'gravacao' => 'CORREDOR',
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                ],
            ],
        ]);
        $cartItem->method('getBuyRequest')->willReturn($buyRequest);

        $productOptionMock = $this->createMock(ProductOption::class);
        $this->productOptionFactoryMock->method('create')->willReturn($productOptionMock);

        $extensionMock = $this->createMock(ProductOptionExtensionInterface::class);
        $this->extensionFactoryMock->method('create')->willReturn($extensionMock);

        $extensionMock->expects($this->once())
            ->method('setAtelieConfiguracao')
            ->with($this->callback(function (ConfigurationInterface $cfg) {
                return $cfg->getSize() === 42 && $cfg->getEngraving() === 'CORREDOR';
            }));

        $cartItem->expects($this->once())
            ->method('setProductOption')
            ->with($productOptionMock);

        $result = $this->processor->processOptions($cartItem);
        $this->assertSame($cartItem, $result);
    }

    public function testProcessOptionsSavesCustomOptionOnItemAndProduct(): void
    {
        $cartItem = $this->createMock(Item::class);
        $cartItem->method('getProductType')->willReturn(Atelie::TYPE_CODE);
        $cartItem->method('getProductOption')->willReturn(null);
        $cartItem->method('getOptionByCode')->with(Atelie::CUSTOM_OPTION_CONFIGURACAO)->willReturn(null);

        $productMock = $this->createMock(Product::class);
        $productMock->method('getId')->willReturn(15);
        $cartItem->method('getProduct')->willReturn($productMock);

        $buyRequest = new DataObject([
            'atelie' => [
                'tamanho' => 39,
                'gravacao' => 'NUVEM',
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                ],
            ],
        ]);
        $cartItem->method('getBuyRequest')->willReturn($buyRequest);

        $productOptionMock = $this->createMock(ProductOption::class);
        $this->productOptionFactoryMock->method('create')->willReturn($productOptionMock);

        $extensionMock = $this->createMock(ProductOptionExtensionInterface::class);
        $this->extensionFactoryMock->method('create')->willReturn($extensionMock);

        $cartItem->expects($this->once())
            ->method('addOption')
            ->with($this->callback(function (array $optionData) {
                return $optionData['code'] === Atelie::CUSTOM_OPTION_CONFIGURACAO
                    && $optionData['product_id'] === 15
                    && str_contains((string) $optionData['value'], '"tamanho":39');
            }));

        $productMock->expects($this->once())
            ->method('addCustomOption')
            ->with(
                Atelie::CUSTOM_OPTION_CONFIGURACAO,
                $this->callback(function (string $serialized) {
                    return str_contains($serialized, '"tamanho":39');
                })
            );

        $result = $this->processor->processOptions($cartItem);
        $this->assertSame($cartItem, $result);
    }
}
