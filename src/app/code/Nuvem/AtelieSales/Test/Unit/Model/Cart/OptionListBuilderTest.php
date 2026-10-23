<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model\Cart;

use Magento\Catalog\Model\Product;
use Magento\Quote\Model\Quote\Item;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie as AtelieType;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OptionListBuilderTest extends TestCase
{
    private const SKU = 'NUVEM-RUNNER';

    private const FULL_CONFIGURATION = '{"tamanho":38,"gravacao":"LUIZ","selecoes":['
        . '{"grupo":"solado","componente":"SOL-TRANSLUCIDO-GELO"},'
        . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"},'
        . '{"grupo":"emblema","componente":"EMB-NUVEM-PESSEGO"},'
        . '{"grupo":"calcanhar","componente":"CAL-ACOLCHOADO-CEU"},'
        . '{"grupo":"biqueira","componente":"BIQ-LISA-CEU"},'
        . '{"grupo":"entressola","componente":"ENT-EVA-NUVEM"},'
        . '{"grupo":"cadarco","componente":"CAD-ELASTICO-MENTA"}'
        . ']}';

    private const EXPECTED_FULL_ROWS = [
        ['label' => 'Tamanho', 'value' => '38'],
        ['label' => 'Gravação', 'value' => 'LUIZ'],
        ['label' => 'Cabedal', 'value' => 'Tricô Lavanda'],
        ['label' => 'Biqueira', 'value' => 'Lisa Céu'],
        ['label' => 'Calcanhar', 'value' => 'Acolchoado Céu'],
        ['label' => 'Cadarço', 'value' => 'Elástico Menta'],
        ['label' => 'Emblema Nuvem', 'value' => 'Nuvem Pêssego'],
        ['label' => 'Entressola', 'value' => 'EVA Nuvem'],
        ['label' => 'Solado', 'value' => 'Translúcido Gelo'],
    ];

    private MockObject&AtelieCatalogInterface $catalog;

    private OptionListBuilder $builder;

    protected function setUp(): void
    {
        $this->catalog = $this->createMock(AtelieCatalogInterface::class);
        $this->catalog->method('getBySku')->with(self::SKU)->willReturn(['grupos' => self::catalogGroups()]);
        $this->builder = new OptionListBuilder(new ConfigurationSerializer(), $this->catalog);
    }

    public function testBuildShowsSizeEngravingAndSevenComponentsInGroupOrder(): void
    {
        $item = $this->createItem(self::FULL_CONFIGURATION);

        $this->assertSame(self::EXPECTED_FULL_ROWS, $this->builder->build($item));
    }

    public function testBuildOmitsEngravingRowWhenThereIsNoEngraving(): void
    {
        $configuration = '{"tamanho":36,"gravacao":null,"selecoes":['
            . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"}'
            . ']}';
        $rows = $this->builder->build($this->createItem($configuration));

        $this->assertCount(2, $rows);
        $this->assertSame('Tamanho', $rows[0]['label']);
        $this->assertSame('Cabedal', $rows[1]['label']);
    }

    public function testBuildReturnsNoRowsWhenItemHasNoConfiguration(): void
    {
        $this->assertSame([], $this->builder->build($this->createItem(null)));
    }

    public function testBuildReturnsNoRowsWhenConfigurationIsNotValidJson(): void
    {
        $this->assertSame([], $this->builder->build($this->createItem('not a json configuration')));
    }

    public function testBuildFallsBackToCodesForUnknownGroupAndComponent(): void
    {
        $configuration = '{"tamanho":38,"gravacao":null,"selecoes":['
            . '{"grupo":"etiqueta","componente":"ETQ-DESCONHECIDA"}'
            . ']}';
        $rows = $this->builder->build($this->createItem($configuration));

        $this->assertSame(
            [
                ['label' => 'Tamanho', 'value' => '38'],
                ['label' => 'etiqueta', 'value' => 'ETQ-DESCONHECIDA'],
            ],
            $rows
        );
    }

    public function testBuildKeepsValuesRawForTheOutputEscapingStep(): void
    {
        $configuration = '{"tamanho":38,"gravacao":"<b>LUIZ</b>","selecoes":['
            . '{"grupo":"cabedal","componente":"CAB-TRICO-LAVANDA"}'
            . ']}';
        $rows = $this->builder->build($this->createItem($configuration));

        $this->assertSame('<b>LUIZ</b>', $rows[1]['value']);
    }

    public function testGetSerializedConfigurationReturnsStoredValue(): void
    {
        $this->assertSame(
            self::FULL_CONFIGURATION,
            $this->builder->getSerializedConfiguration($this->createItem(self::FULL_CONFIGURATION))
        );
    }

    public function testGetSerializedConfigurationReturnsNullWithoutOption(): void
    {
        $this->assertNull($this->builder->getSerializedConfiguration($this->createItem(null)));
    }

    public function testLabelsAreFetchedOncePerSku(): void
    {
        $catalog = $this->createMock(AtelieCatalogInterface::class);
        $catalog->expects($this->once())
            ->method('getBySku')
            ->willReturn(['grupos' => self::catalogGroups()]);
        $builder = new OptionListBuilder(new ConfigurationSerializer(), $catalog);

        $builder->build($this->createItem(self::FULL_CONFIGURATION));
        $builder->build($this->createItem(self::FULL_CONFIGURATION));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function catalogGroups(): array
    {
        return [
            [
                'codigo' => 'cabedal',
                'nome' => 'Cabedal',
                'componentes' => [
                    ['codigo' => 'CAB-TRICO-LAVANDA', 'nome' => 'Tricô Lavanda'],
                ],
            ],
            [
                'codigo' => 'biqueira',
                'nome' => 'Biqueira',
                'componentes' => [
                    ['codigo' => 'BIQ-LISA-CEU', 'nome' => 'Lisa Céu'],
                ],
            ],
            [
                'codigo' => 'calcanhar',
                'nome' => 'Calcanhar',
                'componentes' => [
                    ['codigo' => 'CAL-ACOLCHOADO-CEU', 'nome' => 'Acolchoado Céu'],
                ],
            ],
            [
                'codigo' => 'cadarco',
                'nome' => 'Cadarço',
                'componentes' => [
                    ['codigo' => 'CAD-ELASTICO-MENTA', 'nome' => 'Elástico Menta'],
                ],
            ],
            [
                'codigo' => 'emblema',
                'nome' => 'Emblema Nuvem',
                'componentes' => [
                    ['codigo' => 'EMB-NUVEM-PESSEGO', 'nome' => 'Nuvem Pêssego'],
                ],
            ],
            [
                'codigo' => 'entressola',
                'nome' => 'Entressola',
                'componentes' => [
                    ['codigo' => 'ENT-EVA-NUVEM', 'nome' => 'EVA Nuvem'],
                ],
            ],
            [
                'codigo' => 'solado',
                'nome' => 'Solado',
                'componentes' => [
                    ['codigo' => 'SOL-TRANSLUCIDO-GELO', 'nome' => 'Translúcido Gelo'],
                ],
            ],
        ];
    }

    private function createItem(?string $serialized): MockObject&Item
    {
        $item = $this->createMock(Item::class);
        $item->method('getOptionByCode')->willReturnCallback(
            function (string $code) use ($serialized) {
                if ($serialized === null || $code !== AtelieType::CUSTOM_OPTION_CONFIGURACAO) {
                    return null;
                }
                $option = $this->getMockBuilder(\Magento\Quote\Model\Quote\Item\Option::class)
                    ->disableOriginalConstructor()
                    ->getMock();
                $option->method('getValue')->willReturn($serialized);

                return $option;
            }
        );

        $product = $this->createMock(Product::class);
        $product->method('getSku')->willReturn(self::SKU);
        $item->method('getProduct')->willReturn($product);

        return $item;
    }
}
