<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Block\Checkout\Cart\Item\Renderer;

use Magento\Catalog\Block\Product\ImageBuilder;
use Magento\Catalog\Helper\Product\Configuration as ProductConfiguration;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\View\Element\Message\InterpretationStrategyInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Model\Quote\Item\AbstractItem;
use Nuvem\AtelieSales\Block\Checkout\Cart\Item\Renderer\Atelie;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AtelieTest extends TestCase
{
    private const SERIALIZED_CONFIGURATION = '{"tamanho":38,"gravacao":"LUIZ","selecoes":[]}';
    private const PREVIEW_URL = '/atelie/preview/svg?c=%7B%22tamanho%22%3A38%7D';

    private MockObject&OptionListBuilder $optionListBuilder;

    private MockObject&PreviewInterface $preview;

    private Atelie $renderer;

    protected function setUp(): void
    {
        $this->optionListBuilder = $this->createMock(OptionListBuilder::class);
        $this->preview = $this->createMock(PreviewInterface::class);

        $context = $this->createMock(Context::class);
        $context->method('getLayout')->willReturn($this->createMock(LayoutInterface::class));

        $this->renderer = new Atelie(
            $context,
            $this->createMock(ProductConfiguration::class),
            $this->createMock(CheckoutSession::class),
            $this->createMock(ImageBuilder::class),
            $this->createMock(UrlHelper::class),
            $this->createMock(ManagerInterface::class),
            $this->createMock(PriceCurrencyInterface::class),
            $this->createMock(Manager::class),
            $this->createMock(InterpretationStrategyInterface::class),
            $this->optionListBuilder,
            $this->preview,
            [],
            $this->createMock(ItemResolverInterface::class)
        );
    }

    public function testGetOptionListComesFromTheBuilder(): void
    {
        $item = $this->createMock(AbstractItem::class);
        $rows = [
            ['label' => 'Tamanho', 'value' => '38'],
            ['label' => 'Gravação', 'value' => '<b>LUIZ</b>'],
        ];
        $this->optionListBuilder->method('build')->with($item)->willReturn($rows);
        $this->renderer->setItem($item);

        $this->assertSame($rows, $this->renderer->getOptionList());
    }

    public function testGetPreviewImageUrlIsTheClientCreation(): void
    {
        $item = $this->createMock(AbstractItem::class);
        $this->optionListBuilder->method('getSerializedConfiguration')->with($item)
            ->willReturn(self::SERIALIZED_CONFIGURATION);
        $this->preview->method('getUrl')->with(self::SERIALIZED_CONFIGURATION)->willReturn(self::PREVIEW_URL);
        $this->renderer->setItem($item);

        $this->assertSame(self::PREVIEW_URL, $this->renderer->getPreviewImageUrl());
    }

    public function testGetPreviewUrlFallsBackToEmptyWithoutConfiguration(): void
    {
        $item = $this->createMock(AbstractItem::class);
        $this->optionListBuilder->method('getSerializedConfiguration')->with($item)->willReturn(null);
        $this->renderer->setItem($item);

        $this->assertSame('', $this->renderer->getPreviewImageUrl());
    }
}
