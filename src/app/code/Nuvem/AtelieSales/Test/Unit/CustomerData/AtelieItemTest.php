<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\CustomerData;

use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Msrp\Helper\Data as MsrpHelper;
use Magento\Quote\Model\Quote\Item;
use Nuvem\AtelieSales\CustomerData\AtelieItem;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AtelieItemTest extends TestCase
{
    private const SERIALIZED_CONFIGURATION = '{"tamanho":38,"gravacao":"LUIZ","selecoes":[]}';
    private const PREVIEW_URL = '/atelie/preview/svg?c=%7B%22tamanho%22%3A38%7D';
    private const CATALOG_IMAGE_URL = 'http://localhost/pub/media/catalog/product/base.jpg';

    private MockObject&OptionListBuilder $optionListBuilder;

    private MockObject&PreviewInterface $preview;

    private MockObject&ImageHelper $imageHelper;

    private MockObject&ItemResolverInterface $itemResolver;

    private AtelieItem $itemData;

    protected function setUp(): void
    {
        $this->optionListBuilder = $this->createMock(OptionListBuilder::class);
        $this->preview = $this->createMock(PreviewInterface::class);

        $this->imageHelper = $this->createMock(ImageHelper::class);
        $this->imageHelper->method('init')->willReturnSelf();
        $this->imageHelper->method('getUrl')->willReturn(self::CATALOG_IMAGE_URL);
        $this->imageHelper->method('getLabel')->willReturn('Nuvem Runner');
        $this->imageHelper->method('getWidth')->willReturn(75);
        $this->imageHelper->method('getHeight')->willReturn(75);

        $this->itemResolver = $this->createMock(ItemResolverInterface::class);

        $escaper = $this->createMock(Escaper::class);
        $escaper->method('escapeHtml')->willReturnArgument(0);

        $this->itemData = new AtelieItem(
            $this->imageHelper,
            $this->createMock(MsrpHelper::class),
            $this->createMock(UrlInterface::class),
            $this->createMock(ConfigurationPool::class),
            $this->createMock(CheckoutHelper::class),
            $this->optionListBuilder,
            $this->preview,
            $escaper,
            $this->itemResolver
        );
    }

    public function testGetItemDataShowsRawOptionsAndTheCreationImage(): void
    {
        $item = $this->createItem(true);

        $data = $this->itemData->getItemData($item);
        $this->assertSame('atelie', $data['product_type']);
        $this->assertSame(
            [['label' => 'Tamanho', 'value' => '38']],
            $data['options']
        );
        $this->assertSame(self::PREVIEW_URL, $data['product_image']['src']);
        $this->assertSame(75, $data['product_image']['width']);
        $this->assertSame('Nuvem Runner', $data['product_image']['alt']);
    }

    public function testGetItemDataKeepsCatalogImageWithoutConfiguration(): void
    {
        $item = $this->createItem(false);

        $data = $this->itemData->getItemData($item);

        $this->assertSame([], $data['options']);
        $this->assertSame(self::CATALOG_IMAGE_URL, $data['product_image']['src']);
    }

    public function testEngravingStaysLiteralForTheMinicartTextBinding(): void
    {
        $item = $this->createItem(true, [
            ['label' => 'Gravação', 'value' => '<b>LUIZ</b>'],
        ]);

        $this->assertSame('<b>LUIZ</b>', $this->itemData->getItemData($item)['options'][0]['value']);
    }

    private function createItem(
        bool $withConfiguration,
        array $rows = [['label' => 'Tamanho', 'value' => '38']]
    ): MockObject&Item
    {
        $item = $this->createMock(Item::class);
        $item->method('getProductType')->willReturn('atelie');
        $item->method('getQty')->willReturn(1);
        $item->method('getId')->willReturn(33);
        $item->method('getMessage')->willReturn('');
        $item->method('getCalculationPrice')->willReturn(589.4);
        $item->method('getOptionByCode')->willReturn(null);

        $product = $this->createMock(Product::class);
        $product->method('getName')->willReturn('Nuvem Runner');
        $product->method('getId')->willReturn(5);
        $product->method('getSku')->willReturn('NUVEM-RUNNER');
        $product->method('isVisibleInSiteVisibility')->willReturn(true);
        $urlModel = $this->createMock(\Magento\Framework\UrlInterface::class);
        $urlModel->method('getUrl')->willReturn('http://localhost/nuvem-runner.html');
        $product->method('getUrlModel')->willReturn($urlModel);
        $item->method('getProduct')->willReturn($product);

        $this->itemResolver->method('getFinalProduct')->willReturn($product);

        if ($withConfiguration) {
            $this->optionListBuilder->method('build')->willReturn($rows);
            $this->optionListBuilder->method('getSerializedConfiguration')->willReturn(self::SERIALIZED_CONFIGURATION);
            $this->preview->method('getUrl')->with(self::SERIALIZED_CONFIGURATION)->willReturn(self::PREVIEW_URL);
        } else {
            $this->optionListBuilder->method('build')->willReturn([]);
            $this->optionListBuilder->method('getSerializedConfiguration')->willReturn(null);
        }

        return $item;
    }
}
