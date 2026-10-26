<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\Selection;
use Nuvem\AtelieSales\Model\PriceCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private const DEFAULT_RUNNER_SELECTIONS = [
        ['cabedal', 'CAB-TELA-MENTA'],
        ['calcanhar', 'CAL-LISO-ROSA'],
        ['cadarco', 'CAD-ALGODAO-NUVEM'],
        ['solado', 'SOL-BORRACHA-LAVANDA'],
    ];

    private const PRICED_RUNNER_SELECTIONS = [
        ['cabedal', 'CAB-TRICO-LAVANDA'],
        ['calcanhar', 'CAL-ACOLCHOADO-CEU'],
        ['cadarco', 'CAD-ELASTICO-MENTA'],
        ['solado', 'SOL-TRANSLUCIDO-GELO'],
    ];

    private MockObject&AtelieCatalogInterface $catalog;

    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->catalog = $this->createMock(AtelieCatalogInterface::class);
        $this->calculator = new PriceCalculator($this->catalog);
    }

    public function testValidKitConfigurationCosts58940(): void
    {
        $this->catalog->method('getBySku')->with('NUVEM-RUNNER')->willReturn(self::runnerCatalog());

        $price = $this->calculator->calculate('NUVEM-RUNNER', self::configuration(
            self::PRICED_RUNNER_SELECTIONS,
            'LUIZ'
        ));

        $this->assertEqualsWithDelta(589.40, $price, 0.001);
    }

    public function testAdditionsWithoutEngravingExcludeTheEngravingPrice(): void
    {
        $this->catalog->method('getBySku')->willReturn(self::runnerCatalog());

        $price = $this->calculator->calculate('NUVEM-RUNNER', self::configuration(
            self::PRICED_RUNNER_SELECTIONS
        ));

        $this->assertEqualsWithDelta(559.50, $price, 0.001);
    }

    public function testDefaultSelectionsCostTheBasePriceAlone(): void
    {
        $this->catalog->method('getBySku')->willReturn(self::runnerCatalog());

        $price = $this->calculator->calculate('NUVEM-RUNNER', self::configuration(
            self::DEFAULT_RUNNER_SELECTIONS
        ));

        $this->assertEqualsWithDelta(449.90, $price, 0.001);
    }

    public function testEngravingWithoutSelectionsCostsBasePlusEngraving(): void
    {
        $this->catalog->method('getBySku')->willReturn(self::runnerCatalog());

        $price = $this->calculator->calculate('NUVEM-RUNNER', new Configuration(38, 'LUIZ'));

        $this->assertEqualsWithDelta(479.80, $price, 0.001);
    }

    public function testEngravingOnModelWithoutEngravingSupportFails(): void
    {
        $this->catalog->method('getBySku')->willReturn(self::miniCatalog());

        $this->expectException(LocalizedException::class);

        $this->calculator->calculate('NUVEM-MINI', new Configuration(30, 'LUIZ'));
    }

    public function testUnknownComponentFails(): void
    {
        $this->catalog->method('getBySku')->willReturn(self::runnerCatalog());

        $this->expectException(LocalizedException::class);

        $this->calculator->calculate('NUVEM-RUNNER', new Configuration(38, null, [
            new Selection('solado', 'SOL-NOT-IN-CATALOG'),
        ]));
    }

    public function testUnknownSkuFailsLoudly(): void
    {
        $this->catalog->method('getBySku')->willThrowException(
            new NoSuchEntityException(__('Product with SKU "%1" does not exist.', 'UNKNOWN'))
        );

        $this->expectException(NoSuchEntityException::class);

        $this->calculator->calculate('UNKNOWN', new Configuration(38));
    }

    public function testCatalogIsMemoizedPerSkuAcrossCalculations(): void
    {
        $this->catalog->expects($this->exactly(2))
            ->method('getBySku')
            ->willReturnMap([
                ['NUVEM-RUNNER', self::runnerCatalog()],
                ['NUVEM-MINI', self::miniCatalog()],
            ]);

        $priced = $this->calculator->calculate(
            'NUVEM-RUNNER',
            self::configuration(self::PRICED_RUNNER_SELECTIONS, 'LUIZ')
        );
        $defaults = $this->calculator->calculate(
            'NUVEM-RUNNER',
            self::configuration(self::DEFAULT_RUNNER_SELECTIONS)
        );
        $mini = $this->calculator->calculate('NUVEM-MINI', self::configuration([]));

        $this->assertEqualsWithDelta(589.40, $priced, 0.001);
        $this->assertEqualsWithDelta(449.90, $defaults, 0.001);
        $this->assertEqualsWithDelta(329.90, $mini, 0.001);
    }

    private static function configuration(array $selections, ?string $engraving = null): Configuration
    {
        return new Configuration(
            38,
            $engraving,
            array_map(
                static fn (array $selection): Selection => new Selection($selection[0], $selection[1]),
                $selections
            )
        );
    }

    private static function runnerCatalog(): array
    {
        return [
            'sku' => 'NUVEM-RUNNER',
            'nome' => 'Nuvem Runner',
            'preco_base' => 449.90,
            'tamanho_min' => 34,
            'tamanho_max' => 44,
            'prazo_producao_dias' => 12,
            'gravacao' => ['preco' => 29.90, 'limite' => 12],
            'grupos' => [
                [
                    'codigo' => 'cabedal',
                    'nome' => 'Cabedal',
                    'ordem' => 10,
                    'camada_svg' => 'camada-cabedal',
                    'componentes' => [
                        ['codigo' => 'CAB-TELA-MENTA', 'nome' => 'Tela Menta', 'cor_hex' => '#C6F0DC', 'acabamento' => 'tela', 'preco_adicional' => 0.00, 'padrao' => true],
                        ['codigo' => 'CAB-TRICO-LAVANDA', 'nome' => 'Tricô Lavanda', 'cor_hex' => '#DCCFF7', 'acabamento' => 'trico', 'preco_adicional' => 39.90, 'padrao' => false],
                    ],
                ],
                [
                    'codigo' => 'calcanhar',
                    'nome' => 'Calcanhar',
                    'ordem' => 30,
                    'camada_svg' => 'camada-calcanhar',
                    'componentes' => [
                        ['codigo' => 'CAL-LISO-ROSA', 'nome' => 'Liso Rosa Magenta', 'cor_hex' => '#F7C6E4', 'acabamento' => 'liso', 'preco_adicional' => 0.00, 'padrao' => true],
                        ['codigo' => 'CAL-ACOLCHOADO-CEU', 'nome' => 'Acolchoado Céu', 'cor_hex' => '#A9D6F2', 'acabamento' => 'acolchoado', 'preco_adicional' => 19.90, 'padrao' => false],
                    ],
                ],
                [
                    'codigo' => 'cadarco',
                    'nome' => 'Cadarço',
                    'ordem' => 40,
                    'camada_svg' => 'camada-cadarco',
                    'componentes' => [
                        ['codigo' => 'CAD-ALGODAO-NUVEM', 'nome' => 'Algodão Nuvem', 'cor_hex' => '#FFFFFF', 'acabamento' => 'algodao', 'preco_adicional' => 0.00, 'padrao' => true],
                        ['codigo' => 'CAD-ELASTICO-MENTA', 'nome' => 'Elástico Menta', 'cor_hex' => '#9EDFC0', 'acabamento' => 'elastico', 'preco_adicional' => 14.90, 'padrao' => false],
                    ],
                ],
                [
                    'codigo' => 'solado',
                    'nome' => 'Solado',
                    'ordem' => 70,
                    'camada_svg' => 'camada-solado',
                    'componentes' => [
                        ['codigo' => 'SOL-BORRACHA-LAVANDA', 'nome' => 'Borracha Lavanda', 'cor_hex' => '#DCCFF7', 'acabamento' => 'borracha', 'preco_adicional' => 0.00, 'padrao' => true],
                        ['codigo' => 'SOL-TRANSLUCIDO-GELO', 'nome' => 'Translúcido Gelo', 'cor_hex' => '#EAF4FB', 'acabamento' => 'translucido', 'preco_adicional' => 34.90, 'padrao' => false],
                    ],
                ],
            ],
            'regras' => [],
        ];
    }

    private static function miniCatalog(): array
    {
        return [
            'sku' => 'NUVEM-MINI',
            'nome' => 'Nuvem Mini',
            'preco_base' => 329.90,
            'tamanho_min' => 26,
            'tamanho_max' => 33,
            'prazo_producao_dias' => 10,
            'gravacao' => null,
            'grupos' => [
                [
                    'codigo' => 'solado',
                    'nome' => 'Solado',
                    'ordem' => 70,
                    'camada_svg' => 'camada-solado',
                    'componentes' => [
                        ['codigo' => 'SOL-BORRACHA-LAVANDA', 'nome' => 'Borracha Lavanda', 'cor_hex' => '#DCCFF7', 'acabamento' => 'borracha', 'preco_adicional' => 0.00, 'padrao' => true],
                    ],
                ],
            ],
            'regras' => [],
        ];
    }
}
