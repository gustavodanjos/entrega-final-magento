<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class QuoteItemConfigurationReaderTest extends TestCase
{
    /**
     * @var Item|MockObject
     */
    private MockObject $itemMock;

    private QuoteItemConfigurationReader $reader;

    protected function setUp(): void
    {
        $this->itemMock = $this->createMock(Item::class);
        $this->itemMock->method('getSku')->willReturn('NUVEM-RUNNER');
        $this->reader = new QuoteItemConfigurationReader();
    }

    public function testReturnsComponentQuantitiesMultipliedByItemQty(): void
    {
        $optionMock = $this->createMock(Option::class);
        $optionMock->method('getValue')->willReturn(
            '{"tamanho":40,"gravacao":null,"selecoes":['
            . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"},'
            . '{"grupo":"solado","componente":"SOL-LUA-HOLO"}]}'
        );
        $this->itemMock->method('getOptionByCode')->willReturn($optionMock);
        $this->itemMock->method('getQty')->willReturn(2.0);

        $this->assertSame(
            ['CAB-TRICO-LAVANDA' => 2, 'SOL-LUA-HOLO' => 2],
            $this->reader->getComponentQuantities($this->itemMock)
        );
    }

    public function testSkipsItemsWithoutTheAtelieOption(): void
    {
        $this->itemMock->method('getOptionByCode')->willReturn(null);

        $this->assertSame([], $this->reader->getComponentQuantities($this->itemMock));
    }

    public function testThrowsOnCorruptedConfiguration(): void
    {
        $optionMock = $this->createMock(Option::class);
        $optionMock->method('getValue')->willReturn('{tamanho:40}');
        $this->itemMock->method('getOptionByCode')->willReturn($optionMock);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A configuração do item "NUVEM-RUNNER" está corrompida');

        $this->reader->getComponentQuantities($this->itemMock);
    }
}
