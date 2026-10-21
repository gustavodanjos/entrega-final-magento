<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Model\Product;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Ui\DataProvider\Product\Form\Modifier\AtelieQuantity;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AtelieQuantityTest extends TestCase
{
    private LocatorInterface|MockObject $locatorMock;
    private Product|MockObject $productMock;
    private AtelieQuantity $modifier;

    protected function setUp(): void
    {
        $this->locatorMock = $this->createMock(LocatorInterface::class);
        $this->productMock = $this->createMock(Product::class);
        $this->locatorMock->method('getProduct')->willReturn($this->productMock);

        $this->modifier = new AtelieQuantity($this->locatorMock);
    }

    public function testModifyDataReturnsDataUnchanged(): void
    {
        $data = ['some' => 'data'];
        $this->assertSame($data, $this->modifier->modifyData($data));
    }

    public function testModifyMetaLeavesNonAtelieProductsUnchanged(): void
    {
        $this->productMock->method('getTypeId')->willReturn('simple');
        $meta = ['some' => 'meta'];
        $this->assertSame($meta, $this->modifier->modifyMeta($meta));
    }

    public function testModifyMetaDisablesQuantityForAtelieProduct(): void
    {
        $this->productMock->method('getTypeId')->willReturn(Atelie::TYPE_CODE);

        $meta = [
            'product-details' => [
                'children' => [
                    'quantity_and_stock_status_qty' => [
                        'children' => [
                            'qty' => [
                                'arguments' => [
                                    'data' => [
                                        'config' => ['disabled' => false],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $this->modifier->modifyMeta($meta);

        $qtyConfig = $result['product-details']['children']['quantity_and_stock_status_qty']['children']['qty'];
        $this->assertTrue($qtyConfig['arguments']['data']['config']['disabled']);
        $this->assertNotEmpty($qtyConfig['arguments']['data']['config']['notice']);

        $modalQty = $result['advanced_inventory_modal']['children']['stock_data']['children']['qty'];
        $this->assertEquals(0, $modalQty['arguments']['data']['config']['visible']);
    }
}
