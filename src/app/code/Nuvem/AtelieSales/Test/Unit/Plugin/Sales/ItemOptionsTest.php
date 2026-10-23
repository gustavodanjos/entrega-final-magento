<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Sales;

use Magento\Sales\Block\Adminhtml\Items\Column\DefaultColumn;
use Magento\Sales\Block\Order\Item\Renderer\DefaultRenderer;
use Magento\Sales\Model\Order\Item;
use Nuvem\AtelieSales\Model\Order\ItemOptionsReader;
use Nuvem\AtelieSales\Plugin\Sales\ItemOptions;
use PHPUnit\Framework\TestCase;

class ItemOptionsTest extends TestCase
{
    public function testAccountAndAdminUseHistoricalRowsOnlyForAtelie(): void
    {
        foreach (['atelie', 'simple'] as $productType) {
            $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
            $item->setData('product_type', $productType);
            $reader = $this->createMock(ItemOptionsReader::class);
            $historical = [['label' => 'Tamanho', 'value' => '38']];
            $native = [['label' => 'Nativa', 'value' => 'Original']];
            $reader->expects($productType === 'atelie' ? $this->exactly(2) : $this->never())
                ->method('read')->with($item)->willReturn($historical);
            $plugin = new ItemOptions($reader);
            $account = $this->getMockBuilder(DefaultRenderer::class)->disableOriginalConstructor()
                ->onlyMethods(['getOrderItem'])->getMock();
            $account->method('getOrderItem')->willReturn($item);
            $admin = $this->getMockBuilder(DefaultColumn::class)->disableOriginalConstructor()
                ->onlyMethods(['getItem'])->getMock();
            $admin->method('getItem')->willReturn($item);
            $expected = $productType === 'atelie' ? $historical : $native;
            $this->assertSame($expected, $plugin->afterGetItemOptions($account, $native));
            $this->assertSame($expected, $plugin->afterGetOrderOptions($admin, $native));
        }
    }
}
