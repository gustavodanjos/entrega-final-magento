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

    public function testAroundCheckQuoteItemQtyReturnsNoErrorForAtelieProduct(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn(Atelie::TYPE_CODE);
        $this->productRepoMock->method('getById')->with(2041)->willReturn($product);

        $expectedResult = new DataObject(['has_error' => false]);
        $this->dataObjectFactoryMock->method('create')->willReturn($expectedResult);

        $proceedCalled = false;
        $proceed = function () use (&$proceedCalled) {
            $proceedCalled = true;
            return new DataObject();
        };

        $result = $this->plugin->aroundCheckQuoteItemQty(
            $this->subjectMock,
            $proceed,
            2041,
            1.0,
            1.0,
            0.0
        );

        $this->assertFalse($proceedCalled);
        $this->assertFalse($result->getHasError());
    }

    public function testAroundCheckQuoteItemQtyCallsProceedForOtherProducts(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getTypeId')->willReturn('simple');
        $this->productRepoMock->method('getById')->with(100)->willReturn($product);

        $proceedResult = new DataObject(['from_proceed' => true]);
        $proceed = function ($productId, $itemQty, $qtyToCheck, $origQty, $scopeId) use ($proceedResult) {
            return $proceedResult;
        };

        $result = $this->plugin->aroundCheckQuoteItemQty(
            $this->subjectMock,
            $proceed,
            100,
            2.0,
            2.0,
            0.0
        );

        $this->assertSame($proceedResult, $result);
    }
}
