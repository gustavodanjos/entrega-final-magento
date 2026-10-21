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
        $this->itemMock->method('getProductType')->willReturn('atelie');
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

    public function testSkipsOtherProductTypes(): void
    {
        $item = $this->createMock(Item::class);
        $item->method('getProductType')->willReturn('simple');
        $item->expects($this->never())->method('getOptionByCode');
        $this->assertSame([], $this->reader->getComponentQuantities($item));
    }

    public function testRejectsAtelieItemWithoutConfiguration(): void
    {
        $this->expectException(LocalizedException::class);
        $this->reader->getComponentQuantities($this->itemMock);
    }

    /** @dataProvider invalidConfigurations */
    public function testRejectsInvalidSelectionStructures(string $json): void
    {
        $option = $this->createMock(Option::class);
        $option->method('getValue')->willReturn($json);
        $this->itemMock->method('getOptionByCode')->willReturn($option);
        $this->itemMock->method('getQty')->willReturn(1.0);
        $this->expectException(LocalizedException::class);
        $this->reader->getComponentQuantities($this->itemMock);
    }

    public static function invalidConfigurations(): array
    {
        return array_map(static fn (array $selections): array => [json_encode([
            'tamanho' => 40, 'gravacao' => null, 'selecoes' => $selections,
        ])], [
            [], ['invalid'],
            [['grupo' => 'solado', 'componente' => '']],
            [['grupo' => 'solado', 'componente' => 'SOL-LUA-HOLO'],
             ['grupo' => 'solado', 'componente' => 'SOL-LUA-HOLO']],
        ]);
    }

    /** @dataProvider invalidQuantities */
    public function testRejectsInvalidQuantity(float $qty): void
    {
        $option = $this->createMock(Option::class);
        $option->method('getValue')->willReturn(
            '{"tamanho":40,"gravacao":null,"selecoes":[{"grupo":"solado","componente":"SOL-LUA-HOLO"}]}'
        );
        $this->itemMock->method('getOptionByCode')->willReturn($option);
        $this->itemMock->method('getQty')->willReturn($qty);
        $this->expectException(LocalizedException::class);
        $this->reader->getComponentQuantities($this->itemMock);
    }

    public static function invalidQuantities(): array
    {
        return [[0.0], [-1.0], [1.5], [INF]];
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
