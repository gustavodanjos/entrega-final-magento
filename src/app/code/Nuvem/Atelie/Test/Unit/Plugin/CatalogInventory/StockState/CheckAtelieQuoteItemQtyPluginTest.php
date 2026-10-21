<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Plugin\CatalogInventory\StockState;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Plugin\CatalogInventory\StockState\CheckAtelieQuoteItemQtyPlugin;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CheckAtelieQuoteItemQtyPluginTest extends TestCase
{
    private ProductRepositoryInterface|MockObject $productRepoMock;
    private DataObjectFactory|MockObject $dataObjectFactoryMock;
    private StockStateInterface|MockObject $subjectMock;
    private CheckAtelieQuoteItemQtyPlugin $plugin;

    protected function setUp(): void
    {
        $this->productRepoMock = $this->createMock(ProductRepositoryInterface::class);
        $this->dataObjectFactoryMock = $this->createMock(DataObjectFactory::class);
        $this->subjectMock = $this->createMock(StockStateInterface::class);

        $this->plugin = new CheckAtelieQuoteItemQtyPlugin(
            $this->productRepoMock,
            $this->dataObjectFactoryMock
        );
    }

    public function testAroundCheckQuoteItemQtyReturnsNoErrorForSalableAtelieProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn(Atelie::TYPE_CODE);
        $product->method('isSalable')->willReturn(true);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($product);

        $expectedResult = new DataObject();
        $this->dataObjectFactoryMock->method('create')->willReturn($expectedResult);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return new DataObject();
        };

        $result = $this->plugin->aroundCheckQuoteItemQty($this->subjectMock, $proceed, 2041, 1.0, 1.0, 0.0);

        $this->assertFalse($proceedCalled);
        $this->assertFalse($result->getHasError());
    }

    public function testAroundCheckQuoteItemQtyReturnsErrorForZeroOrNegativeQuantity(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn(Atelie::TYPE_CODE);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($product);
        $this->dataObjectFactoryMock->method('create')->willReturnCallback(fn() => new DataObject());

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return new DataObject();
        };

        $resultZero = $this->plugin->aroundCheckQuoteItemQty($this->subjectMock, $proceed, 2041, 0.0, 0.0, 0.0);
        $this->assertFalse($proceedCalled);
        $this->assertTrue($resultZero->getHasError());
        $this->assertEquals('qty_min', $resultZero->getErrorCode());

        $resultNeg = $this->plugin->aroundCheckQuoteItemQty($this->subjectMock, $proceed, 2041, -1.0, -1.0, 0.0);
        $this->assertTrue($resultNeg->getHasError());
        $this->assertEquals('qty_min', $resultNeg->getErrorCode());
    }

    public function testAroundCheckQuoteItemQtyReturnsErrorForDecimalQuantity(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn(Atelie::TYPE_CODE);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($product);
        $this->dataObjectFactoryMock->method('create')->willReturnCallback(fn() => new DataObject());

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return new DataObject();
        };

        $result = $this->plugin->aroundCheckQuoteItemQty($this->subjectMock, $proceed, 2041, 1.5, 1.5, 0.0);
        $this->assertFalse($proceedCalled);
        $this->assertTrue($result->getHasError());
        $this->assertEquals('qty_decimal', $result->getErrorCode());
    }

    public function testAroundCheckQuoteItemQtyReturnsErrorForUnsalableAtelieProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn(Atelie::TYPE_CODE);
        $product->method('isSalable')->willReturn(false);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($product);

        $expectedResult = new DataObject();
        $this->dataObjectFactoryMock->method('create')->willReturn($expectedResult);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return new DataObject();
        };

        $result = $this->plugin->aroundCheckQuoteItemQty($this->subjectMock, $proceed, 2041, 1.0, 1.0, 0.0);

        $this->assertFalse($proceedCalled);
        $this->assertTrue($result->getHasError());
        $this->assertEquals('out_stock', $result->getErrorCode());
    }

    public function testAroundCheckQuoteItemQtyCallsProceedForOtherProducts(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $this->productRepoMock->method('getById')->with(100)->willReturn($product);

        $proceedResult = new DataObject(['from_proceed' => true]);
        $proceed = fn() => $proceedResult;

        $result = $this->plugin->aroundCheckQuoteItemQty($this->subjectMock, $proceed, 100, 2.0, 2.0, 0.0);
        $this->assertSame($proceedResult, $result);
    }

    public function testAroundCheckQuoteItemQtyCallsProceedWhenProductNotFound(): void
    {
        $this->productRepoMock->method('getById')->willThrowException(new NoSuchEntityException());

        $proceedResult = new DataObject(['from_proceed' => true]);
        $proceed = fn() => $proceedResult;

        $result = $this->plugin->aroundCheckQuoteItemQty($this->subjectMock, $proceed, 999, 1.0, 1.0, 0.0);
        $this->assertSame($proceedResult, $result);
    }
}
