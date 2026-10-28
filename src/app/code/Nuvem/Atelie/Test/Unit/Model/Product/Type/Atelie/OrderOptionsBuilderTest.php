<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model\Product\Type\Atelie;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie\OrderOptionsBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderOptionsBuilderTest extends TestCase
{
    private GroupRepositoryInterface|MockObject $groupRepoMock;
    private ComponentRepositoryInterface|MockObject $compRepoMock;
    private JsonSerializer $serializer;
    private LoggerInterface|MockObject $loggerMock;
    private OrderOptionsBuilder $builder;

    protected function setUp(): void
    {
        $this->groupRepoMock = $this->createMock(GroupRepositoryInterface::class);
        $this->compRepoMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->serializer = new JsonSerializer();
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->builder = new OrderOptionsBuilder(
            $this->groupRepoMock,
            $this->compRepoMock,
            $this->serializer,
            $this->loggerMock
        );
    }

    public function testReturnsOptionsUnchangedWhenNoConfig(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getCustomOption')->willReturn(null);

        $options = $this->builder->build($product, ['existing' => 'val']);
        $this->assertEquals(['existing' => 'val'], $options);
    }

    public function testBuildsReadableOptionsFromCustomOption(): void
    {
        $product = $this->createMock(Product::class);
        $customOption = $this->createMock(OptionInterface::class);
        $config = [
            'tamanho' => 40,
            'gravacao' => 'NUVEM',
            'selecoes' => [
                ['grupo' => 'cabedal', 'componente' => 'CAB-TRICO-AZUL']
            ]
        ];
        $customOption->method('getValue')->willReturn(json_encode($config));

        $product->method('getCustomOption')->willReturnCallback(function ($code) use ($customOption) {
            return $code === 'atelie_configuracao' ? $customOption : null;
        });

        $group = $this->createMock(GroupInterface::class);
        $group->method('getNome')->willReturn('Cabedal Tricô');
        $this->groupRepoMock->method('getByCodigo')->with('cabedal')->willReturn($group);

        $comp = $this->createMock(ComponentInterface::class);
        $comp->method('getNome')->willReturn('Azul Petróleo');
        $comp->method('getCorHex')->willReturn('#BEE3F8');
        $this->compRepoMock->method('getByCodigo')->with('CAB-TRICO-AZUL')->willReturn($comp);

        $result = $this->builder->build($product, []);

        $this->assertArrayHasKey('atelie_configuracao', $result);
        $this->assertArrayHasKey('attributes_info', $result);
        $this->assertCount(3, $result['attributes_info']);
        $this->assertEquals('Tamanho', $result['attributes_info'][0]['label']);
        $this->assertEquals('40', $result['attributes_info'][0]['value']);
        $this->assertEquals('Gravação', $result['attributes_info'][1]['label']);
        $this->assertEquals('NUVEM', $result['attributes_info'][1]['value']);
        $this->assertEquals('Cabedal Tricô', $result['attributes_info'][2]['label']);
        $this->assertEquals('Azul Petróleo', $result['attributes_info'][2]['value']);
        $this->assertSame($result['attributes_info'], $result['additional_options']);
        $snapshot = $result[OrderOptionsBuilder::SNAPSHOT_KEY];
        $this->assertSame(['label' => 'Tamanho', 'value' => '40'], $snapshot[0]);
        $this->assertSame([
            'label' => 'Cabedal Tricô',
            'value' => 'Azul Petróleo',
            'grupo' => 'cabedal',
            'componente' => 'CAB-TRICO-AZUL',
            'cor_hex' => '#BEE3F8',
        ], $snapshot[2]);
        $this->assertArrayNotHasKey('cor_hex', $result['additional_options'][2]);
    }

    public function testKeepsNumericZeroAsFilledEngraving(): void
    {
        $product = $this->createMock(Product::class);
        $customOption = $this->createMock(OptionInterface::class);
        $customOption->method('getValue')->willReturn(json_encode([
            'tamanho' => 38, 'gravacao' => '0', 'selecoes' => [],
        ]));
        $product->method('getCustomOption')->willReturn($customOption);
        $result = $this->builder->build($product);
        $this->assertSame(['label' => 'Gravação', 'value' => '0'], $result['additional_options'][1]);
        $this->assertSame($result['additional_options'], $result[OrderOptionsBuilder::SNAPSHOT_KEY]);
    }

}
