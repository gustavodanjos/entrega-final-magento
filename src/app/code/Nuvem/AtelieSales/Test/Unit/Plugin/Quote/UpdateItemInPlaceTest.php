<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Quote;

use Magento\Framework\DataObject;
use Magento\Framework\App\Request\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Nuvem\AtelieSales\Model\Cart\ItemUpdater;
use Nuvem\AtelieSales\Plugin\Quote\UpdateItemInPlace;
use PHPUnit\Framework\TestCase;

class UpdateItemInPlaceTest extends TestCase
{
    public function testOtherProductTypesKeepTheCoreFlow(): void
    {
        $quote = $this->createMock(Quote::class);
        $item = $this->createMock(Item::class);
        $item->method('getProductType')->willReturn('simple');
        $quote->method('getItemById')->with(42)->willReturn($item);
        $updater = $this->createMock(ItemUpdater::class);
        $updater->expects($this->never())->method('update');
        $request = new DataObject(['qty' => 2]);
        $called = false;
        $result = ($this->createPlugin($updater))->aroundUpdateItem(
            $quote,
            function ($id, $actualRequest, $params) use (&$called, $request, $item) {
                $called = true;
                $this->assertSame(42, $id);
                $this->assertSame($request, $actualRequest);
                $this->assertSame(['test' => true], $params);
                return $item;
            },
            42,
            $request,
            ['test' => true]
        );
        $this->assertTrue($called);
        $this->assertSame($item, $result);
    }

    public function testAtelieNeverCallsTheCoreMergePath(): void
    {
        $quote = $this->createMock(Quote::class);
        $item = $this->createMock(Item::class);
        $item->method('getProductType')->willReturn('atelie');
        $quote->method('getItemById')->with(42)->willReturn($item);
        $request = new DataObject(['atelie' => ['tamanho' => 38]]);
        $updater = $this->createMock(ItemUpdater::class);
        $updater->expects($this->once())->method('update')->with($quote, $item, $request)->willReturn($item);
        $result = ($this->createPlugin($updater))->aroundUpdateItem(
            $quote,
            function () {
                $this->fail('Core updateItem can replace or merge the original line.');
            },
            42,
            $request
        );
        $this->assertSame($item, $result);
    }

    public static function routesOutsideEdit(): array
    {
        return [
            ['checkout_cart_updatePost'],
            ['checkout_cart_updateItemQty'],
            ['catalog_product_view'],
            ['rest_cart_save'],
        ];
    }

    #[DataProvider('routesOutsideEdit')]
    public function testOtherRoutesKeepRequestedQuantityAndUpdatingParameters(string $route): void
    {
        $quote = $this->createMock(Quote::class);
        $quote->expects($this->never())->method('getItemById');
        $updater = $this->createMock(ItemUpdater::class);
        $updater->expects($this->never())->method('update');
        $request = new DataObject(['qty' => 99, 'atelie' => ['tamanho' => 38]]);
        $params = ['current_config' => ['options' => [7 => 'existing-value']]];
        $result = $this->createPlugin($updater, $route)->aroundUpdateItem(
            $quote,
            function ($id, $actualRequest, $actualParams) use ($request, $params) {
                $this->assertSame(42, $id);
                $this->assertSame($request, $actualRequest);
                $this->assertSame(99, $actualRequest->getQty());
                $this->assertSame($params, $actualParams);
                return 'core-result';
            },
            42,
            $request,
            $params
        );
        $this->assertSame('core-result', $result);
    }

    private function createPlugin(
        ItemUpdater $updater,
        string $route = 'checkout_cart_updateItemOptions'
    ): UpdateItemInPlace {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn($route);
        return new UpdateItemInPlace($updater, $request);
    }
}
