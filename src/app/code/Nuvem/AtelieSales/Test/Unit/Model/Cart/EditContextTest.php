<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model\Cart;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Checkout\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\AtelieSales\Model\Cart\EditContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EditContextTest extends TestCase
{
    public static function configurations(): array
    {
        return [[true, true], [true, false], [false, true]];
    }

    #[DataProvider('configurations')]
    public function testReadsOrderOptionsOnlyForTheCurrentQuotesItem(bool $owned, bool $valid): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('checkout_cart_configure');
        $request->method('getParam')->willReturnMap([['id', null, 42], ['product_id', null, 7]]);
        $quote = $this->createMock(Quote::class);
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()
            ->onlyMethods(['getProductType', 'getId', 'getQty', 'getOptionByCode', 'getProduct'])
            ->addMethods(['getProductId'])->getMock();
        $item->method('getProductId')->willReturn(7);
        $item->method('getProductType')->willReturn('atelie');
        $item->method('getId')->willReturn(42);
        $item->method('getQty')->willReturn(3.0);
        $item->method('getOptionByCode')->willReturn($this->createMock(Option::class));
        $quote->method('getItemById')->with(42)->willReturn($owned ? $item : null);
        $session = $this->createMock(Session::class);
        $session->method('getQuote')->willReturn($quote);
        $configuration = ['tamanho' => 38, 'gravacao' => 'NUVEM',
            'selecoes' => [['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA']]];
        $type = $this->createMock(AbstractType::class);
        $type->expects($owned ? $this->once() : $this->never())->method('getOrderOptions')
            ->willReturn(['atelie_configuracao' => $valid ? $configuration : ['tamanho' => 'invalid']]);
        $product = $this->createMock(Product::class);
        $product->method('getTypeInstance')->willReturn($type);
        $item->method('getProduct')->willReturn($product);
        $context = new EditContext($session, $request, new ConfigurationSerializer(), new Json());
        if (!$owned || !$valid) {
            $this->expectException(LocalizedException::class);
        }
        $options = $context->getItemOptions();
        $this->assertSame(42, $options['item_id']);
        $this->assertSame(3.0, $options['qty']);
        $this->assertSame($configuration, $options['configuration']);
    }

    public function testNormalProductPagesDoNotReadTheCheckoutSession(): void
    {
        $request = $this->createMock(Http::class);
        $request->method('getFullActionName')->willReturn('catalog_product_view');
        $session = $this->createMock(Session::class);
        $session->expects($this->never())->method('getQuote');
        $context = new EditContext($session, $request, new ConfigurationSerializer(), new Json());
        $this->assertSame([], $context->getItemOptions());
    }
}
