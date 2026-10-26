<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Plugin\Sales;

use Magento\Framework\Escaper;
use Magento\Sales\Block\Adminhtml\Items\Column\Name;
use Magento\Sales\Block\Order\Item\Renderer\DefaultRenderer;
use Magento\Sales\Model\Order\Item;
use Nuvem\AtelieSales\Plugin\Sales\OptionText;
use PHPUnit\Framework\TestCase;

class OptionTextTest extends TestCase
{
    public function testPlainAtelieTextSurvivesNativeHtmlAwareEscaping(): void
    {
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $item->setData('product_type', 'atelie');
        $account = $this->getMockBuilder(DefaultRenderer::class)->disableOriginalConstructor()
            ->onlyMethods(['getOrderItem'])->getMock();
        $account->method('getOrderItem')->willReturn($item);
        $admin = $this->getMockBuilder(Name::class)->disableOriginalConstructor()
            ->onlyMethods(['getItem'])->getMock();
        $admin->method('getItem')->willReturn($item);
        $escaper = new Escaper();
        $plugin = new OptionText($escaper);
        $value = '<a href="https://example.invalid">LUIZ</a> & ' . str_repeat('Nome longo ', 10);
        $expected = ['value' => $escaper->escapeHtml($value)];
        $this->assertSame($expected, $plugin->afterGetFormatedOptionValue($account, [], ['value' => $value]));
        $this->assertSame($expected, $plugin->afterGetFormattedOption($admin, [], $value));
    }

    public function testNativeFormattingIsPreservedForOtherProductsAndCustomOptions(): void
    {
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $item->setData('product_type', 'simple');
        $account = $this->getMockBuilder(DefaultRenderer::class)->disableOriginalConstructor()
            ->onlyMethods(['getOrderItem'])->getMock();
        $account->method('getOrderItem')->willReturn($item);
        $native = ['value' => 'Native', 'full_view' => 'Full native value'];
        $plugin = new OptionText(new Escaper());
        $this->assertSame($native, $plugin->afterGetFormatedOptionValue($account, $native, ['value' => 'Other']));
        $admin = $this->getMockBuilder(Name::class)->disableOriginalConstructor()
            ->onlyMethods(['getItem'])->getMock();
        $admin->method('getItem')->willReturn($item);
        $this->assertSame($native, $plugin->afterGetFormattedOption($admin, $native, 'Other'));
        $quoteItem = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item::class)
            ->disableOriginalConstructor()->onlyMethods([])->getMock();
        $quoteColumn = $this->getMockBuilder(Name::class)->disableOriginalConstructor()
            ->onlyMethods(['getItem'])->getMock();
        $quoteColumn->method('getItem')->willReturn($quoteItem);
        $this->assertSame($native, $plugin->afterGetFormattedOption($quoteColumn, $native, 'Quote option'));
        $item->setData('product_type', 'atelie');
        foreach ([['value' => 'File', 'option_id' => 42], ['value' => 'Custom', 'custom_view' => true],
            ['value' => ['One', 'Two']], 'Direct value'] as $option) {
            $this->assertSame($native, $plugin->afterGetFormatedOptionValue($account, $native, $option));
        }
    }
}
