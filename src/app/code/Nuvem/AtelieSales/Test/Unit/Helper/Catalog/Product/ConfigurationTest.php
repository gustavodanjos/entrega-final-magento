<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Helper\Catalog\Product;

use Magento\Catalog\Model\Product\Configuration\Item\ItemInterface;
use Magento\Framework\Escaper;
use Nuvem\AtelieSales\Helper\Catalog\Product\Configuration;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    private MockObject&OptionListBuilder $optionListBuilder;

    private Configuration $helper;

    protected function setUp(): void
    {
        $this->optionListBuilder = $this->createMock(OptionListBuilder::class);
        $this->helper = new Configuration($this->optionListBuilder, new Escaper());
    }

    public function testGetOptionsEscapesValuesForTheCheckoutHtmlTemplate(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $this->optionListBuilder->method('build')->with($item)->willReturn([
            ['label' => 'Tamanho', 'value' => '38'],
            ['label' => 'Gravação', 'value' => '<b>LUIZ</b>'],
        ]);

        $expectedEngraving = htmlspecialchars('<b>LUIZ</b>', ENT_QUOTES, 'UTF-8');
        $this->assertSame(
            [
                ['label' => 'Tamanho', 'value' => '38'],
                ['label' => 'Gravação', 'value' => $expectedEngraving],
            ],
            $this->helper->getOptions($item)
        );
    }

    public function testGetOptionsReturnsNoRowsWithoutConfiguration(): void
    {
        $item = $this->createMock(ItemInterface::class);
        $this->optionListBuilder->method('build')->with($item)->willReturn([]);

        $this->assertSame([], $this->helper->getOptions($item));
    }
}
