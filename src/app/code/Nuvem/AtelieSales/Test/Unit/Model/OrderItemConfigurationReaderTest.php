<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Item as OrderItem;
use Nuvem\AtelieSales\Model\OrderItemConfigurationReader;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderItemConfigurationReaderTest extends TestCase
{
    /**
     * @var OrderItem|MockObject
     */
    private MockObject $itemMock;

    private OrderItemConfigurationReader $reader;

    protected function setUp(): void
    {
        $this->itemMock = $this->createMock(OrderItem::class);
        $this->itemMock->method('getSku')->willReturn('NUVEM-RUNNER');
        $this->reader = new OrderItemConfigurationReader();
    }

    public function testReturnsComponentQuantitiesFromConfigurationArray(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => [
                'tamanho' => 40,
                'gravacao' => null,
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                    ['grupo' => 'solado', 'componente' => 'SOL-LUA-HOLO'],
                ],
            ],
        ]);
        $this->itemMock->method('getQtyOrdered')->willReturn(2.0);

        $this->assertSame(
            ['CAB-TRICO-LAVANDA' => 2, 'SOL-LUA-HOLO' => 2],
            $this->reader->getComponentQuantities($this->itemMock)
        );
    }

    public function testReturnsComponentQuantitiesFromJsonEncodedOption(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'configuracao' => json_encode([
                'tamanho' => 38,
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                ],
            ]),
        ]);
        $this->itemMock->method('getQtyOrdered')->willReturn(1.0);

        $this->assertSame(
            ['CAB-TRICO-LAVANDA' => 1],
            $this->reader->getComponentQuantities($this->itemMock)
        );
    }

    public function testUsesExplicitQtyWhenProvided(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => [
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                ],
            ],
        ]);
        $this->itemMock->method('getQtyOrdered')->willReturn(3.0);

        $this->assertSame(
            ['CAB-TRICO-LAVANDA' => 1],
            $this->reader->getComponentQuantities($this->itemMock, 1)
        );
    }

    public function testReturnsEmptyWhenQtyIsZeroOrNegative(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => [
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-LAVANDA'],
                ],
            ],
        ]);

        $this->assertSame([], $this->reader->getComponentQuantities($this->itemMock, 0));
    }

    public function testReturnsEmptyForNonAtelieItem(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([]);

        $this->assertSame([], $this->reader->getComponentQuantities($this->itemMock));
        $this->assertSame([], $this->reader->getComponentCodes($this->itemMock));
    }

    public function testReturnsEmptyWhenProductOptionsIsNotArray(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn(null);

        $this->assertSame([], $this->reader->getComponentQuantities($this->itemMock));
    }

    public function testThrowsWhenJsonIsMalformed(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => '{invalid-json',
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A configuração do item "NUVEM-RUNNER" está corrompida');

        $this->reader->getComponentQuantities($this->itemMock);
    }

    public function testThrowsWhenSelectionsIsMissing(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => ['tamanho' => 40],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A configuração do item "NUVEM-RUNNER" está corrompida');

        $this->reader->getComponentQuantities($this->itemMock);
    }

    public function testThrowsWhenComponentCodeIsInvalid(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => [
                'selecoes' => [
                    ['grupo' => 'cabedal', 'componente' => ''],
                ],
            ],
        ]);

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('A configuração do item "NUVEM-RUNNER" está corrompida');

        $this->reader->getComponentQuantities($this->itemMock);
    }
    public function testThrowsForAtelieItemWithoutConfiguration(): void
    {
        $this->itemMock->method('getProductType')->willReturn('atelie');
        $this->itemMock->method('getProductOptions')->willReturn(null);
        $this->expectException(LocalizedException::class);
        $this->reader->getComponentQuantities($this->itemMock);
    }

    public function testThrowsForEmptySelections(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => ['selecoes' => []],
        ]);
        $this->expectException(LocalizedException::class);
        $this->reader->getComponentQuantities($this->itemMock);
    }

    public function testThrowsForMalformedSelection(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => ['selecoes' => ['invalid']],
        ]);
        $this->expectException(LocalizedException::class);
        $this->reader->getComponentQuantities($this->itemMock);
    }

    public function testCountsRepeatedComponentsInStoredConfiguration(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'atelie_configuracao' => ['selecoes' => [
                ['componente' => 'SOL-LUA-HOLO'], ['componente' => 'SOL-LUA-HOLO'],
            ]],
        ]);
        self::assertSame(['SOL-LUA-HOLO' => 4], $this->reader->getComponentQuantities($this->itemMock, 2));
    }

    public function testReadsHistoricalBuyRequestConfiguration(): void
    {
        $this->itemMock->method('getProductOptions')->willReturn([
            'info_buyRequest' => ['atelie' => ['selections' => [['component' => 'SOL-LUA-HOLO']]]],
        ]);
        self::assertSame(['SOL-LUA-HOLO' => 1], $this->reader->getComponentQuantities($this->itemMock, 1));
    }

}
