<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\View;

use Magento\Framework\DataObject;
use Magento\Framework\Escaper;
use Magento\Sales\Model\Order\Item;
use Nuvem\AtelieSales\Model\Order\ItemOptionsReader;
use Nuvem\AtelieSales\ViewModel\Order\ItemOptions;
use PHPUnit\Framework\TestCase;

class EmailFichaTest extends TestCase
{
    public function testTemplateEscapesCustomerAndCatalogTextAndRejectsInjectedColor(): void
    {
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()
            ->onlyMethods(['getProductOptions'])->getMock();
        $item->method('getProductOptions')->willReturn([
            ItemOptionsReader::SNAPSHOT_KEY => [
                ['label' => 'Gravação', 'value' => '<b>LUIZ & NUVEM</b>'],
                ['label' => '<img src=x>', 'value' => '<script>alert(1)</script>',
                    'cor_hex' => '#FFFFFF;display:none'],
            ],
        ]);
        $block = new DataObject([
            'view_model' => new ItemOptions(new ItemOptionsReader()),
            'item' => $item,
        ]);
        $escaper = new Escaper();
        ob_start();
        try {
            include dirname(__DIR__, 3) . '/view/frontend/templates/email/items/ficha.phtml';
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        $this->assertStringContainsString('&lt;b&gt;LUIZ &amp; NUVEM&lt;/b&gt;', $html);
        $this->assertStringContainsString('&lt;img src=x&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('display:none', $html);
        $this->assertStringNotContainsString('background-color:', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('&amp;lt;', $html);
    }
}
