<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\AtelieCatalog;
use Nuvem\Atelie\Model\Config;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\Selection;
use Nuvem\AtelieSales\Model\PriceCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Proves the F2.1 calculator consumes the exact shape the real F1.1
 * AtelieCatalog produces.
 */
class PriceCalculatorWithRealCatalogTest extends TestCase
{
    private const PRICED_RUNNER_SELECTIONS = [
        ['cabedal', 'CAB-TRICO-LAVANDA'],
        ['calcanhar', 'CAL-ACOLCHOADO-CEU'],
        ['cadarco', 'CAD-ELASTICO-MENTA'],
        ['solado', 'SOL-TRANSLUCIDO-GELO'],
    ];

    private const DEFAULT_RUNNER_SELECTIONS = [
        ['cabedal', 'CAB-TELA-MENTA'],
        ['calcanhar', 'CAL-LISO-ROSA'],
        ['cadarco', 'CAD-ALGODAO-NUVEM'],
        ['solado', 'SOL-BORRACHA-LAVANDA'],
    ];

    private MockObject&GroupRepositoryInterface $groupRepository;
    private MockObject&ComponentRepositoryInterface $componentRepository;
    private MockObject&RuleRepositoryInterface $ruleRepository;
    private MockObject&ProductRepositoryInterface $productRepository;
    private MockObject&ScopeConfigInterface $scopeConfig;

    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        $this->groupRepository = $this->createMock(GroupRepositoryInterface::class);
        $this->componentRepository = $this->createMock(ComponentRepositoryInterface::class);
        $this->ruleRepository = $this->createMock(RuleRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);

        $searchCriteriaBuilder = $this->createMock(SearchCriteriaBuilder::class);
        $searchCriteriaBuilder->method('addFilter')->willReturnSelf();
        $searchCriteriaBuilder->method('addSortOrder')->willReturnSelf();
        $searchCriteriaBuilder->method('create')->willReturn($this->createMock(SearchCriteria::class));

        $sortOrderBuilder = $this->createMock(SortOrderBuilder::class);
        $sortOrderBuilder->method('setField')->willReturnSelf();
        $sortOrderBuilder->method('setAscendingDirection')->willReturnSelf();
        $sortOrderBuilder->method('create')->willReturn($this->createMock(SortOrder::class));

        $catalog = new AtelieCatalog(
            $this->groupRepository,
            $this->componentRepository,
            $this->ruleRepository,
            $searchCriteriaBuilder,
            $sortOrderBuilder,
            $this->productRepository,
            $this->scopeConfig
        );

        $this->calculator = new PriceCalculator($catalog);
    }

    public function testRunnerKitWithRealCatalogPrices58940(): void
    {
        $this->mockProductExists('NUVEM-RUNNER', '449.90');
        $this->mockEngravingConfig('29.90', '12');
        $this->mockRunnerGroups();

        $price = $this->calculator->calculate('NUVEM-RUNNER', new Configuration(
            38,
            'LUIZ',
            array_map(
                static fn (array $selection): Selection => new Selection($selection[0], $selection[1]),
                self::PRICED_RUNNER_SELECTIONS
            )
        ));

        $this->assertEqualsWithDelta(589.40, $price, 0.001);
    }

    public function testRunnerDefaultsWithRealCatalogCostTheBasePriceAlone(): void
    {
        $this->mockProductExists('NUVEM-RUNNER', '449.90');
        $this->mockEngravingConfig('29.90', '12');
        $this->mockRunnerGroups();

        $price = $this->calculator->calculate('NUVEM-RUNNER', new Configuration(
            38,
            null,
            array_map(
                static fn (array $selection): Selection => new Selection($selection[0], $selection[1]),
                self::DEFAULT_RUNNER_SELECTIONS
            )
        ));

        $this->assertEqualsWithDelta(449.90, $price, 0.001);
    }

    public function testEngravingOnMiniWithRealCatalogFails(): void
    {
        $this->productRepository->method('get')
            ->with('NUVEM-MINI')
            ->willThrowException(new NoSuchEntityException(__('Not found')));
        $this->mockEngravingConfig('29.90', '12');
        $this->mockEmptyGroups();

        $this->expectException(LocalizedException::class);

        $this->calculator->calculate('NUVEM-MINI', new Configuration(30, 'LUIZ'));
    }

    public function testBasePriceComesFromTheProductPriceAttribute(): void
    {
        $this->mockProductExists('NUVEM-RUNNER', '499.00');
        $this->mockEngravingConfig('29.90', '12');
        $this->mockEmptyGroups();

        $price = $this->calculator->calculate('NUVEM-RUNNER', new Configuration(38));

        $this->assertEqualsWithDelta(499.00, $price, 0.001);
    }

    private function mockProductExists(string $sku, string $price): void
    {
        $product = $this->createMock(ProductInterface::class);
        $product->method('getName')->willReturn('Nuvem ' . $sku);
        $product->method('getPrice')->willReturn($price);
        $product->method('getCustomAttribute')->willReturn(null);

        $this->productRepository->expects($this->once())
            ->method('get')
            ->with($sku)
            ->willReturn($product);
    }

    private function mockEngravingConfig(string $price, string $limit): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): ?string => match ($path) {
                Config::XML_PATH_ENGRAVING_PRICE => $price,
                Config::XML_PATH_ENGRAVING_LIMIT => $limit,
                default => null,
            }
        );
    }

    private function mockEmptyGroups(): void
    {
        $groupResults = $this->createMock(GroupSearchResultsInterface::class);
        $groupResults->method('getItems')->willReturn([]);
        $this->groupRepository->method('getList')->willReturn($groupResults);

        $componentResults = $this->createMock(ComponentSearchResultsInterface::class);
        $componentResults->method('getItems')->willReturn([]);
        $this->componentRepository->method('getList')->willReturn($componentResults);

        $ruleResults = $this->createMock(RuleSearchResultsInterface::class);
        $ruleResults->method('getItems')->willReturn([]);
        $this->ruleRepository->method('getList')->willReturn($ruleResults);
    }

    private function mockRunnerGroups(): void
    {
        $groups = [
            $this->groupMock(1, 'cabedal'),
            $this->groupMock(2, 'calcanhar'),
            $this->groupMock(3, 'cadarco'),
            $this->groupMock(4, 'solado'),
        ];
        $components = [
            $this->componentMock(101, 1, 'CAB-TELA-MENTA', 0.00, true),
            $this->componentMock(102, 1, 'CAB-TRICO-LAVANDA', 39.90, false),
            $this->componentMock(201, 2, 'CAL-LISO-ROSA', 0.00, true),
            $this->componentMock(202, 2, 'CAL-ACOLCHOADO-CEU', 19.90, false),
            $this->componentMock(301, 3, 'CAD-ALGODAO-NUVEM', 0.00, true),
            $this->componentMock(302, 3, 'CAD-ELASTICO-MENTA', 14.90, false),
            $this->componentMock(401, 4, 'SOL-BORRACHA-LAVANDA', 0.00, true),
            $this->componentMock(402, 4, 'SOL-TRANSLUCIDO-GELO', 34.90, false),
        ];

        $groupResults = $this->createMock(GroupSearchResultsInterface::class);
        $groupResults->method('getItems')->willReturn($groups);
        $this->groupRepository->method('getList')->willReturn($groupResults);

        $componentResults = $this->createMock(ComponentSearchResultsInterface::class);
        $componentResults->method('getItems')->willReturn($components);
        $this->componentRepository->method('getList')->willReturn($componentResults);

        $ruleResults = $this->createMock(RuleSearchResultsInterface::class);
        $ruleResults->method('getItems')->willReturn([]);
        $this->ruleRepository->method('getList')->willReturn($ruleResults);
    }

    private function groupMock(int $id, string $codigo): GroupInterface
    {
        $group = $this->createMock(GroupInterface::class);
        $group->method('getGrupoId')->willReturn($id);
        $group->method('getCodigo')->willReturn($codigo);
        $group->method('getNome')->willReturn(strtoupper($codigo));
        $group->method('getOrdem')->willReturn($id * 10);
        $group->method('getCamadaSvg')->willReturn('camada-' . $codigo);

        return $group;
    }

    private function componentMock(
        int $id,
        int $groupId,
        string $codigo,
        float $precoAdicional,
        bool $padrao
    ): ComponentInterface {
        $component = $this->createMock(ComponentInterface::class);
        $component->method('getComponenteId')->willReturn($id);
        $component->method('getGrupoId')->willReturn($groupId);
        $component->method('getCodigo')->willReturn($codigo);
        $component->method('getNome')->willReturn($codigo);
        $component->method('getCorHex')->willReturn('#FFFFFF');
        $component->method('getAcabamento')->willReturn('tela');
        $component->method('getPrecoAdicional')->willReturn($precoAdicional);
        $component->method('isPadrao')->willReturn($padrao);

        return $component;
    }
}
