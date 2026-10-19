<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ComponentSearchResultsInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\AtelieCatalog;
use Nuvem\Atelie\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AtelieCatalogTest extends TestCase
{
    private GroupRepositoryInterface&MockObject $groupRepositoryMock;
    private ComponentRepositoryInterface&MockObject $componentRepositoryMock;
    private RuleRepositoryInterface&MockObject $ruleRepositoryMock;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilderMock;
    private SortOrderBuilder&MockObject $sortOrderBuilderMock;
    private ProductRepositoryInterface&MockObject $productRepositoryMock;
    private ScopeConfigInterface&MockObject $scopeConfigMock;
    private AtelieCatalog $catalog;

    protected function setUp(): void
    {
        $this->groupRepositoryMock = $this->createMock(GroupRepositoryInterface::class);
        $this->componentRepositoryMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->ruleRepositoryMock = $this->createMock(RuleRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->sortOrderBuilderMock = $this->createMock(SortOrderBuilder::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $sortOrderMock = $this->createMock(SortOrder::class);
        $this->sortOrderBuilderMock->method('setField')->willReturnSelf();
        $this->sortOrderBuilderMock->method('setAscendingDirection')->willReturnSelf();
        $this->sortOrderBuilderMock->method('create')->willReturn($sortOrderMock);

        $searchCriteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('addSortOrder')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('create')->willReturn($searchCriteriaMock);

        $this->catalog = new AtelieCatalog(
            $this->groupRepositoryMock,
            $this->componentRepositoryMock,
            $this->ruleRepositoryMock,
            $this->searchCriteriaBuilderMock,
            $this->sortOrderBuilderMock,
            $this->productRepositoryMock,
            $this->scopeConfigMock
        );
    }

    public function testGetBySkuWithLoadedProduct(): void
    {
        $productMock = $this->createMock(ProductInterface::class);
        $productMock->method('getName')->willReturn('Nuvem Runner Custom');
        $productMock->method('getPrice')->willReturn(499.00);

        $minAttr = $this->createMock(AttributeInterface::class);
        $minAttr->method('getValue')->willReturn('35');
        $maxAttr = $this->createMock(AttributeInterface::class);
        $maxAttr->method('getValue')->willReturn('43');
        $prazoAttr = $this->createMock(AttributeInterface::class);
        $prazoAttr->method('getValue')->willReturn('14');
        $gravAttr = $this->createMock(AttributeInterface::class);
        $gravAttr->method('getValue')->willReturn('1');

        $productMock->method('getCustomAttribute')->willReturnCallback(
            function (string $code) use ($minAttr, $maxAttr, $prazoAttr, $gravAttr) {
                return match ($code) {
                    'atelie_tamanho_min' => $minAttr,
                    'atelie_tamanho_max' => $maxAttr,
                    'atelie_prazo_producao' => $prazoAttr,
                    'atelie_permite_gravacao' => $gravAttr,
                    default => null,
                };
            }
        );

        $this->productRepositoryMock->expects($this->once())
            ->method('get')
            ->with('NUVEM-RUNNER')
            ->willReturn($productMock);

        $this->scopeConfigMock->method('getValue')->willReturnCallback(
            function (string $path) {
                return match ($path) {
                    Config::XML_PATH_ENGRAVING_PRICE => '35.00',
                    Config::XML_PATH_ENGRAVING_LIMIT => '10',
                    default => null,
                };
            }
        );

        // Mock groups
        $groupMock = $this->createMock(GroupInterface::class);
        $groupMock->method('getGrupoId')->willReturn(1);
        $groupMock->method('getCodigo')->willReturn('cabedal');
        $groupMock->method('getNome')->willReturn('Cabedal');
        $groupMock->method('getOrdem')->willReturn(10);
        $groupMock->method('getCamadaSvg')->willReturn('camada-cabedal');

        $groupResultsMock = $this->createMock(GroupSearchResultsInterface::class);
        $groupResultsMock->method('getItems')->willReturn([$groupMock]);
        $this->groupRepositoryMock->method('getList')->willReturn($groupResultsMock);

        // Mock components
        $compMock = $this->createMock(ComponentInterface::class);
        $compMock->method('getComponenteId')->willReturn(101);
        $compMock->method('getGrupoId')->willReturn(1);
        $compMock->method('getCodigo')->willReturn('CAB-TELA-MENTA');
        $compMock->method('getNome')->willReturn('Tela Menta');
        $compMock->method('getCorHex')->willReturn('#C6F0DC');
        $compMock->method('getAcabamento')->willReturn('tela');
        $compMock->method('getPrecoAdicional')->willReturn(0.00);
        $compMock->method('isPadrao')->willReturn(true);

        $compResultsMock = $this->createMock(ComponentSearchResultsInterface::class);
        $compResultsMock->method('getItems')->willReturn([$compMock]);
        $this->componentRepositoryMock->method('getList')->willReturn($compResultsMock);

        // Mock rules
        $ruleMock = $this->createMock(RuleInterface::class);
        $ruleMock->method('getCodigo')->willReturn('R01');
        $ruleMock->method('getTipo')->willReturn('bloqueia');
        $ruleMock->method('getOrigemComponenteId')->willReturn(101);
        $ruleMock->method('getAlvoTipo')->willReturn('componente');
        $ruleMock->method('getAlvo')->willReturn('SOL-LUA-HOLO');
        $ruleMock->method('getMensagem')->willReturn('Regra 01 mensagem');

        $ruleResultsMock = $this->createMock(RuleSearchResultsInterface::class);
        $ruleResultsMock->method('getItems')->willReturn([$ruleMock]);
        $this->ruleRepositoryMock->method('getList')->willReturn($ruleResultsMock);

        $result = $this->catalog->getBySku('NUVEM-RUNNER');

        $this->assertSame('NUVEM-RUNNER', $result['sku']);
        $this->assertSame('Nuvem Runner Custom', $result['nome']);
        $this->assertSame(499.00, $result['preco_base']);
        $this->assertSame(35, $result['tamanho_min']);
        $this->assertSame(43, $result['tamanho_max']);
        $this->assertSame(14, $result['prazo_producao_dias']);
        $this->assertSame(['preco' => 35.00, 'limite' => 10], $result['gravacao']);

        $this->assertCount(1, $result['grupos']);
        $this->assertSame('cabedal', $result['grupos'][0]['codigo']);
        $this->assertCount(1, $result['grupos'][0]['componentes']);
        $this->assertSame('CAB-TELA-MENTA', $result['grupos'][0]['componentes'][0]['codigo']);

        $this->assertCount(1, $result['regras']);
        $this->assertSame('R01', $result['regras'][0]['codigo']);
        $this->assertSame('CAB-TELA-MENTA', $result['regras'][0]['origem']);
    }

    public function testGetBySkuFallbackToDefaultsWhenProductNotYetInCatalog(): void
    {
        $this->productRepositoryMock->expects($this->once())
            ->method('get')
            ->with('NUVEM-MINI')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $groupResultsMock = $this->createMock(GroupSearchResultsInterface::class);
        $groupResultsMock->method('getItems')->willReturn([]);
        $this->groupRepositoryMock->method('getList')->willReturn($groupResultsMock);

        $compResultsMock = $this->createMock(ComponentSearchResultsInterface::class);
        $compResultsMock->method('getItems')->willReturn([]);
        $this->componentRepositoryMock->method('getList')->willReturn($compResultsMock);

        $ruleResultsMock = $this->createMock(RuleSearchResultsInterface::class);
        $ruleResultsMock->method('getItems')->willReturn([]);
        $this->ruleRepositoryMock->method('getList')->willReturn($ruleResultsMock);

        $result = $this->catalog->getBySku('NUVEM-MINI');

        $this->assertSame('NUVEM-MINI', $result['sku']);
        $this->assertSame('Nuvem Mini', $result['nome']);
        $this->assertSame(329.90, $result['preco_base']);
        $this->assertSame(26, $result['tamanho_min']);
        $this->assertSame(33, $result['tamanho_max']);
        $this->assertSame(10, $result['prazo_producao_dias']);
        $this->assertNull($result['gravacao']);
    }

    public function testGetBySkuThrowsNoSuchEntityExceptionWhenUnknown(): void
    {
        $this->productRepositoryMock->expects($this->once())
            ->method('get')
            ->with('UNKNOWN-SKU')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Product with SKU "UNKNOWN-SKU" does not exist.');

        $this->catalog->getBySku('UNKNOWN-SKU');
    }
}
