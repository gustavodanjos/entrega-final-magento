<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Pricing\Render;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Pricing\Renderer\SalableResolverInterface;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\MinimalPriceCalculatorInterface;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\Price\PriceInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Framework\Pricing\Render as PricingRender;
use Magento\Framework\Pricing\Render\RendererPool;
use Magento\Framework\Pricing\SaleableInterface;
use Magento\Framework\View\Element\Template\Context;
use Nuvem\Atelie\Pricing\Render\FinalPriceBox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FinalPriceBoxTest extends TestCase
{
    /**
     * @var Context|MockObject
     */
    private Context|MockObject $contextMock;

    /**
     * @var SaleableInterface|MockObject
     */
    private SaleableInterface|MockObject $saleableItemMock;

    /**
     * @var PriceInterface|MockObject
     */
    private PriceInterface|MockObject $priceMock;

    /**
     * @var RendererPool|MockObject
     */
    private RendererPool|MockObject $rendererPoolMock;

    /**
     * @var SalableResolverInterface|MockObject
     */
    private SalableResolverInterface|MockObject $salableResolverMock;

    /**
     * @var MinimalPriceCalculatorInterface|MockObject
     */
    private MinimalPriceCalculatorInterface|MockObject $minimalPriceCalculatorMock;

    /**
     * @var PriceInfoInterface|MockObject
     */
    private PriceInfoInterface|MockObject $priceInfoMock;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->saleableItemMock = $this->createMock(Product::class);
        $this->priceMock = $this->createMock(PriceInterface::class);
        $this->rendererPoolMock = $this->createMock(RendererPool::class);
        $this->salableResolverMock = $this->createMock(SalableResolverInterface::class);
        $this->minimalPriceCalculatorMock = $this->createMock(MinimalPriceCalculatorInterface::class);
        $this->priceInfoMock = $this->createMock(PriceInfoInterface::class);

        $this->saleableItemMock->method('getPriceInfo')->willReturn($this->priceInfoMock);
    }

    private function createPriceBox(array $data = []): FinalPriceBox
    {
        return new FinalPriceBox(
            $this->contextMock,
            $this->saleableItemMock,
            $this->priceMock,
            $this->rendererPoolMock,
            $data,
            $this->salableResolverMock,
            $this->minimalPriceCalculatorMock
        );
    }

    public function testIsProductListReturnsTrueWhenDataFlagSet(): void
    {
        $box = $this->createPriceBox(['is_product_list' => true]);
        $this->assertTrue($box->isProductList());
    }

    public function testIsProductListReturnsFalseWhenDataFlagFalse(): void
    {
        $box = $this->createPriceBox(['is_product_list' => false]);
        $this->assertFalse($box->isProductList());
    }

    public function testIsProductListFallsBackToZoneItemList(): void
    {
        $box = $this->createPriceBox(['zone' => PricingRender::ZONE_ITEM_LIST]);
        $this->assertTrue($box->isProductList());
    }

    public function testIsProductListReturnsFalseForZoneItemViewWhenNoDataFlag(): void
    {
        $box = $this->createPriceBox(['zone' => PricingRender::ZONE_ITEM_VIEW]);
        $this->assertFalse($box->isProductList());
    }

    public function testShowStartingAtReturnsTrueOnProductList(): void
    {
        $box = $this->createPriceBox([
            'is_product_list' => true,
            'zone' => PricingRender::ZONE_ITEM_LIST
        ]);
        $this->assertTrue($box->showStartingAt());
    }

    public function testShowStartingAtReturnsFalseOnProductViewZoneWhenNotProductList(): void
    {
        $box = $this->createPriceBox([
            'is_product_list' => false,
            'zone' => PricingRender::ZONE_ITEM_VIEW
        ]);
        $this->assertFalse($box->showStartingAt());
    }

    public function testShowStartingAtReturnsTrueOnOtherNonProductViewZone(): void
    {
        $box = $this->createPriceBox([
            'zone' => 'custom_listing_zone'
        ]);
        $this->assertTrue($box->showStartingAt());
    }

    public function testGetStartingAtLabelReturnsExpectedPhrase(): void
    {
        $box = $this->createPriceBox();
        $this->assertEquals('a partir de', (string)$box->getStartingAtLabel());
    }

    public function testHasSpecialPriceReturnsTrueWhenSpecialPriceMapMatchesInListing(): void
    {
        $this->saleableItemMock->method('getId')->willReturn(2041);
        $box = $this->createPriceBox([
            'is_product_list' => true,
            'special_price_map' => [2041 => true]
        ]);
        $this->assertTrue($box->hasSpecialPrice());
    }

    public function testHasSpecialPriceReturnsFalseWhenSpecialPriceMapFalseInListing(): void
    {
        $this->saleableItemMock->method('getId')->willReturn(2041);
        $box = $this->createPriceBox([
            'is_product_list' => true,
            'special_price_map' => [2041 => false]
        ]);
        $this->assertFalse($box->hasSpecialPrice());
    }

    public function testHasSpecialPriceComparesAmountsWhenNoMapPresent(): void
    {
        $regularAmount = $this->createMock(AmountInterface::class);
        $regularAmount->method('getValue')->willReturn(449.90);
        $regularPrice = $this->createMock(PriceInterface::class);
        $regularPrice->method('getAmount')->willReturn($regularAmount);

        $finalAmount = $this->createMock(AmountInterface::class);
        $finalAmount->method('getValue')->willReturn(399.90);
        $finalPrice = $this->createMock(PriceInterface::class);
        $finalPrice->method('getAmount')->willReturn($finalAmount);

        $this->priceInfoMock->method('getPrice')
            ->willReturnMap([
                [RegularPrice::PRICE_CODE, $regularPrice],
                [FinalPrice::PRICE_CODE, $finalPrice]
            ]);

        $box = $this->createPriceBox([
            'is_product_list' => false,
            'zone' => PricingRender::ZONE_ITEM_VIEW
        ]);
        $this->assertTrue($box->hasSpecialPrice());
    }

    public function testHasSpecialPriceReturnsFalseWhenFinalPriceEqualsOrGreaterThanRegular(): void
    {
        $regularAmount = $this->createMock(AmountInterface::class);
        $regularAmount->method('getValue')->willReturn(449.90);
        $regularPrice = $this->createMock(PriceInterface::class);
        $regularPrice->method('getAmount')->willReturn($regularAmount);

        $finalAmount = $this->createMock(AmountInterface::class);
        $finalAmount->method('getValue')->willReturn(449.90);
        $finalPrice = $this->createMock(PriceInterface::class);
        $finalPrice->method('getAmount')->willReturn($finalAmount);

        $this->priceInfoMock->method('getPrice')
            ->willReturnMap([
                [RegularPrice::PRICE_CODE, $regularPrice],
                [FinalPrice::PRICE_CODE, $finalPrice]
            ]);

        $box = $this->createPriceBox([
            'is_product_list' => false,
            'zone' => PricingRender::ZONE_ITEM_VIEW
        ]);
        $this->assertFalse($box->hasSpecialPrice());
    }
}
