<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Checkout\Model\Cart;

use Magento\Checkout\Model\Cart\ImageProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use Nuvem\AtelieSales\Plugin\Checkout\Model\Cart\PreviewImageProvider;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PreviewImageProviderTest extends TestCase
{
    private const QUOTE_ID = 7;
    private const SERIALIZED_CONFIGURATION = '{"tamanho":38,"gravacao":"LUIZ","selecoes":[]}';
    private const PREVIEW_URL = '/atelie/preview/svg?c=%7B%22tamanho%22%3A38%7D';

    private MockObject&CheckoutSession $checkoutSession;

    private MockObject&OptionListBuilder $optionListBuilder;

    private MockObject&PreviewInterface $preview;

    private PreviewImageProvider $plugin;

    protected function setUp(): void
    {
        $this->checkoutSession = $this->createMock(CheckoutSession::class);
        $this->optionListBuilder = $this->createMock(OptionListBuilder::class);
        $this->preview = $this->createMock(PreviewInterface::class);
        $this->plugin = new PreviewImageProvider(
            $this->checkoutSession,
            $this->optionListBuilder,
            $this->preview
        );
    }

    public function testAfterGetImagesReplacesOnlyAtelieItems(): void
    {
        $this->givenSessionQuoteWithItems();
        $result = [
            33 => ['src' => 'catalog-33.jpg', 'alt' => 'Nuvem Runner', 'width' => 75, 'height' => 75],
            45 => ['src' => 'catalog-45.jpg', 'alt' => 'Nuvem Mini', 'width' => 75, 'height' => 75],
        ];

        $result = $this->plugin->afterGetImages(
            $this->createMock(ImageProvider::class),
            $result,
            (string) self::QUOTE_ID
        );

        $this->assertSame(self::PREVIEW_URL, $result[33]['src']);
        $this->assertSame(75, $result[33]['width']);
        $this->assertSame('Nuvem Runner', $result[33]['alt']);
        $this->assertSame('catalog-45.jpg', $result[45]['src']);
    }

    public function testAfterGetImagesIgnoresCartsOtherThanTheSessionOne(): void
    {
        $this->givenSessionQuoteWithItems();
        $result = [33 => ['src' => 'catalog-33.jpg', 'alt' => 'Nuvem Runner', 'width' => 75, 'height' => 75]];

        $result = $this->plugin->afterGetImages(
            $this->createMock(ImageProvider::class),
            $result,
            999
        );

        $this->assertSame('catalog-33.jpg', $result[33]['src']);
    }

    private function givenSessionQuoteWithItems(): void
    {
        $atelieItem = $this->createMock(Item::class);
        $atelieItem->method('getItemId')->willReturn(33);
        $simpleItem = $this->createMock(Item::class);
        $simpleItem->method('getItemId')->willReturn(45);

        $quote = $this->createMock(Quote::class);
        $quote->method('getId')->willReturn(self::QUOTE_ID);
        $quote->method('getAllItems')->willReturn([$atelieItem, $simpleItem]);

        $this->checkoutSession->method('getQuote')->willReturn($quote);

        $this->optionListBuilder->method('getSerializedConfiguration')->willReturnCallback(
            fn ($item) => $item === $atelieItem ? self::SERIALIZED_CONFIGURATION : null
        );
        $this->preview->method('getUrl')->with(self::SERIALIZED_CONFIGURATION)->willReturn(self::PREVIEW_URL);
    }
}
