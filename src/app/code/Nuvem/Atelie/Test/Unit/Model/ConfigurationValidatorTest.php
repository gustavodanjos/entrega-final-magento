<?php
/**
 * Copyright © Nuvem, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
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
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\GroupInterface;
use Nuvem\Atelie\Api\Data\GroupSearchResultsInterface;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\RuleSearchResultsInterface;
use Nuvem\Atelie\Api\Data\SelectionInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\Config;
use Nuvem\Atelie\Model\ConfigurationValidator;
use Nuvem\Atelie\Model\ValidationResultFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurationValidatorTest extends TestCase
{
    /**
     * @var GroupRepositoryInterface|MockObject
     */
    private MockObject $groupRepositoryMock;

    /**
     * @var ComponentRepositoryInterface|MockObject
     */
    private MockObject $componentRepositoryMock;

    /**
     * @var RuleRepositoryInterface|MockObject
     */
    private MockObject $ruleRepositoryMock;

    /**
     * @var SearchCriteriaBuilder|MockObject
     */
    private MockObject $searchCriteriaBuilderMock;

    /**
     * @var SortOrderBuilder|MockObject
     */
    private MockObject $sortOrderBuilderMock;

    /**
     * @var ProductRepositoryInterface|MockObject
     */
    private MockObject $productRepositoryMock;

    /**
     * @var ScopeConfigInterface|MockObject
     */
    private MockObject $scopeConfigMock;

    /**
     * @var ValidationResultFactory|MockObject
     */
    private MockObject $validationResultFactoryMock;

    /**
     * @var ConfigurationValidator
     */
    private ConfigurationValidator $validator;

    /**
     * @var array<string, GroupInterface|MockObject>
     */
    private array $groups = [];

    /**
     * @var array<string, ComponentInterface|MockObject>
     */
    private array $components = [];

    /**
     * @var array<RuleInterface|MockObject>
     */
    private array $rules = [];

    public static function setUpBeforeClass(): void
    {
        if (class_exists(\Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader::class)) {
            $generatorIo = new \Magento\Framework\Code\Generator\Io(
                new \Magento\Framework\Filesystem\Driver\File(),
                sys_get_temp_dir() . '/generated/code'
            );
            $generatedCodeAutoloader = new \Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader(
                [
                    new \Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesGenerator(),
                    new \Magento\Framework\TestFramework\Unit\Autoloader\ExtensionAttributesInterfaceGenerator(),
                    new \Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator(),
                    new \Magento\Framework\TestFramework\Unit\Autoloader\ProxyGenerator()
                ],
                $generatorIo
            );
            spl_autoload_register([$generatedCodeAutoloader, 'load']);
        }
    }

    protected function setUp(): void
    {
        $this->groupRepositoryMock = $this->createMock(GroupRepositoryInterface::class);
        $this->componentRepositoryMock = $this->createMock(ComponentRepositoryInterface::class);
        $this->ruleRepositoryMock = $this->createMock(RuleRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = $this->createMock(SearchCriteriaBuilder::class);
        $this->sortOrderBuilderMock = $this->createMock(SortOrderBuilder::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->validationResultFactoryMock = $this->createMock(ValidationResultFactory::class);

        $sortOrderMock = $this->createMock(SortOrder::class);
        $this->sortOrderBuilderMock->method('setField')->willReturnSelf();
        $this->sortOrderBuilderMock->method('setAscendingDirection')->willReturnSelf();
        $this->sortOrderBuilderMock->method('create')->willReturn($sortOrderMock);

        $searchCriteriaMock = $this->createMock(SearchCriteria::class);
        $this->searchCriteriaBuilderMock->method('addFilter')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('addSortOrder')->willReturnSelf();
        $this->searchCriteriaBuilderMock->method('create')->willReturn($searchCriteriaMock);

        $this->validator = new ConfigurationValidator(
            $this->groupRepositoryMock,
            $this->componentRepositoryMock,
            $this->ruleRepositoryMock,
            $this->searchCriteriaBuilderMock,
            $this->sortOrderBuilderMock,
            $this->productRepositoryMock,
            $this->scopeConfigMock,
            null
        );

        $this->initDefaultCatalogFixtures();
    }

    private function initDefaultCatalogFixtures(): void
    {
        // 7 groups
        $groupDefinitions = [
            ['id' => 1, 'codigo' => 'cabedal', 'nome' => 'Cabedal', 'ordem' => 10],
            ['id' => 2, 'codigo' => 'biqueira', 'nome' => 'Biqueira', 'ordem' => 20],
            ['id' => 3, 'codigo' => 'calcanhar', 'nome' => 'Calcanhar', 'ordem' => 30],
            ['id' => 4, 'codigo' => 'cadarco', 'nome' => 'Cadarço', 'ordem' => 40],
            ['id' => 5, 'codigo' => 'emblema', 'nome' => 'Emblema Nuvem', 'ordem' => 50],
            ['id' => 6, 'codigo' => 'entressola', 'nome' => 'Entressola', 'ordem' => 60],
            ['id' => 7, 'codigo' => 'solado', 'nome' => 'Solado', 'ordem' => 70],
        ];

        $this->groups = [];
        foreach ($groupDefinitions as $def) {
            $grp = $this->createMock(GroupInterface::class);
            $grp->method('getGrupoId')->willReturn($def['id']);
            $grp->method('getCodigo')->willReturn($def['codigo']);
            $grp->method('getNome')->willReturn($def['nome']);
            $grp->method('getOrdem')->willReturn($def['ordem']);
            $this->groups[$def['codigo']] = $grp;
        }

        $groupResultsMock = $this->createMock(GroupSearchResultsInterface::class);
        $groupResultsMock->method('getItems')->willReturn(array_values($this->groups));
        $this->groupRepositoryMock->method('getList')->willReturn($groupResultsMock);

        // Components
        $componentDefs = [
            ['id' => 4, 'grupo_id' => 1, 'codigo' => 'CAB-TRICO-LAVANDA', 'nome' => 'Tricô Lavanda', 'acabamento' => 'trico', 'estoque' => 50, 'ativo' => true],
            ['id' => 1, 'grupo_id' => 1, 'codigo' => 'CAB-TELA-MENTA', 'nome' => 'Tela Menta', 'acabamento' => 'tela', 'estoque' => 50, 'ativo' => true],
            ['id' => 6, 'grupo_id' => 1, 'codigo' => 'CAB-CAMURCA-BAUNILHA', 'nome' => 'Camurça Baunilha', 'acabamento' => 'camurca', 'estoque' => 40, 'ativo' => true],

            ['id' => 7, 'grupo_id' => 2, 'codigo' => 'BIQ-LISA-CEU', 'nome' => 'Lisa Céu', 'acabamento' => 'liso', 'estoque' => 80, 'ativo' => true],
            ['id' => 10, 'grupo_id' => 2, 'codigo' => 'BIQ-VERNIZ-ROSA', 'nome' => 'Verniz Rosa', 'acabamento' => 'verniz', 'estoque' => 30, 'ativo' => true],

            ['id' => 11, 'grupo_id' => 3, 'codigo' => 'CAL-ACOLCHOADO-CEU', 'nome' => 'Acolchoado Céu', 'acabamento' => 'acolchoado', 'estoque' => 90, 'ativo' => true],

            ['id' => 15, 'grupo_id' => 4, 'codigo' => 'CAD-ALGODAO-NUVEM', 'nome' => 'Algodão Nuvem', 'acabamento' => 'algodao', 'estoque' => 100, 'ativo' => true],
            ['id' => 18, 'grupo_id' => 4, 'codigo' => 'CAD-ELASTICO-MENTA', 'nome' => 'Elástico Menta', 'acabamento' => 'elastico', 'estoque' => 120, 'ativo' => true],

            ['id' => 19, 'grupo_id' => 5, 'codigo' => 'EMB-NUVEM-PESSEGO', 'nome' => 'Nuvem Pêssego', 'acabamento' => 'liso', 'estoque' => 60, 'ativo' => true],
            ['id' => 22, 'grupo_id' => 5, 'codigo' => 'EMB-NUVEM-GLITTER', 'nome' => 'Nuvem Glitter', 'acabamento' => 'glitter', 'estoque' => 50, 'ativo' => true],

            ['id' => 23, 'grupo_id' => 6, 'codigo' => 'ENT-EVA-NUVEM', 'nome' => 'EVA Nuvem', 'acabamento' => 'eva', 'estoque' => 70, 'ativo' => true],
            ['id' => 26, 'grupo_id' => 6, 'codigo' => 'ENT-AR-VISIVEL', 'nome' => 'Câmara de Ar Visível', 'acabamento' => 'ar', 'estoque' => 30, 'ativo' => true],

            ['id' => 29, 'grupo_id' => 7, 'codigo' => 'SOL-TRANSLUCIDO-GELO', 'nome' => 'Translúcido Gelo', 'acabamento' => 'translucido', 'estoque' => 45, 'ativo' => true],
            ['id' => 27, 'grupo_id' => 7, 'codigo' => 'SOL-BORRACHA-MENTA', 'nome' => 'Borracha Menta', 'acabamento' => 'borracha', 'estoque' => 50, 'ativo' => true],
            ['id' => 31, 'grupo_id' => 7, 'codigo' => 'SOL-LUA-HOLO', 'nome' => 'Lua Holográfico', 'acabamento' => 'holografico', 'estoque' => 10, 'ativo' => true],
        ];

        $this->components = [];
        foreach ($componentDefs as $def) {
            $comp = $this->createMock(ComponentInterface::class);
            $comp->method('getComponenteId')->willReturn($def['id']);
            $comp->method('getGrupoId')->willReturn($def['grupo_id']);
            $comp->method('getCodigo')->willReturn($def['codigo']);
            $comp->method('getNome')->willReturn($def['nome']);
            $comp->method('getAcabamento')->willReturn($def['acabamento']);
            $comp->method('getEstoque')->willReturn($def['estoque']);
            $comp->method('isAtivo')->willReturn($def['ativo']);
            $this->components[$def['codigo']] = $comp;
        }

        $this->componentRepositoryMock->method('getByCodigo')->willReturnCallback(
            function (string $codigo) {
                if (isset($this->components[$codigo])) {
                    return $this->components[$codigo];
                }
                throw new NoSuchEntityException(__('Component with code "%1" does not exist.', $codigo));
            }
        );

        // Rules R01 to R05
        $ruleDefs = [
            [
                'codigo' => 'R01',
                'tipo' => 'bloqueia',
                'origem_id' => 6, // CAB-CAMURCA-BAUNILHA
                'alvo_tipo' => 'componente',
                'alvo' => 'SOL-LUA-HOLO',
                'mensagem' => 'A cola do solado holográfico mancha a camurça. Escolha outro solado ou outro cabedal.',
            ],
            [
                'codigo' => 'R02',
                'tipo' => 'exige',
                'origem_id' => 18, // CAD-ELASTICO-MENTA
                'alvo_tipo' => 'acabamento',
                'alvo' => 'trico',
                'mensagem' => 'O cadarço elástico só segura em cabedal de tricô.',
            ],
            [
                'codigo' => 'R03',
                'tipo' => 'bloqueia',
                'origem_id' => 22, // EMB-NUVEM-GLITTER
                'alvo_tipo' => 'componente',
                'alvo' => 'BIQ-VERNIZ-ROSA',
                'mensagem' => 'Glitter com verniz pesa no visual. Fique com um dos dois.',
            ],
            [
                'codigo' => 'R04',
                'tipo' => 'exige',
                'origem_id' => 26, // ENT-AR-VISIVEL
                'alvo_tipo' => 'componente',
                'alvo' => 'SOL-TRANSLUCIDO-GELO',
                'mensagem' => 'A câmara de ar só aparece com o solado translúcido.',
            ],
            [
                'codigo' => 'R05',
                'tipo' => 'bloqueia',
                'origem_id' => 26, // ENT-AR-VISIVEL
                'alvo_tipo' => 'gravacao',
                'alvo' => '*',
                'mensagem' => 'Não dá para gravar sobre a câmara de ar.',
            ],
        ];

        $this->rules = [];
        foreach ($ruleDefs as $rDef) {
            $r = $this->createMock(RuleInterface::class);
            $r->method('getCodigo')->willReturn($rDef['codigo']);
            $r->method('getTipo')->willReturn($rDef['tipo']);
            $r->method('getOrigemComponenteId')->willReturn($rDef['origem_id']);
            $r->method('getAlvoTipo')->willReturn($rDef['alvo_tipo']);
            $r->method('getAlvo')->willReturn($rDef['alvo']);
            $r->method('getMensagem')->willReturn($rDef['mensagem']);
            $r->method('isAtivo')->willReturn(true);
            $this->rules[] = $r;
        }

        $ruleResultsMock = $this->createMock(RuleSearchResultsInterface::class);
        $ruleResultsMock->method('getItems')->willReturn($this->rules);
        $this->ruleRepositoryMock->method('getList')->willReturn($ruleResultsMock);

        // Product repository default
        $this->productRepositoryMock->method('get')->willReturnCallback(
            function (string $sku) {
                if ($sku === 'NUVEM-RUNNER' || $sku === 'NUVEM-MINI') {
                    $prod = $this->createMock(ProductInterface::class);
                    $prod->method('getTypeId')->willReturn('atelie');
                    $prod->method('getName')->willReturn($sku === 'NUVEM-RUNNER' ? 'Nuvem Runner' : 'Nuvem Mini');

                    $minAttr = $this->createMock(AttributeInterface::class);
                    $minAttr->method('getValue')->willReturn($sku === 'NUVEM-RUNNER' ? '34' : '26');

                    $maxAttr = $this->createMock(AttributeInterface::class);
                    $maxAttr->method('getValue')->willReturn($sku === 'NUVEM-RUNNER' ? '44' : '33');

                    $gravAttr = $this->createMock(AttributeInterface::class);
                    $gravAttr->method('getValue')->willReturn($sku === 'NUVEM-RUNNER' ? '1' : '0');

                    $prod->method('getCustomAttribute')->willReturnCallback(
                        function (string $code) use ($minAttr, $maxAttr, $gravAttr) {
                            return match ($code) {
                                'atelie_tamanho_min' => $minAttr,
                                'atelie_tamanho_max' => $maxAttr,
                                'atelie_permite_gravacao' => $gravAttr,
                                default => null,
                            };
                        }
                    );
                    return $prod;
                }

                if ($sku === 'SIMPLE-PRODUCT') {
                    $prod = $this->createMock(ProductInterface::class);
                    $prod->method('getTypeId')->willReturn('simple');
                    $prod->method('getName')->willReturn('Simple Product');
                    $prod->method('getCustomAttribute')->willReturn(null);
                    return $prod;
                }

                throw new NoSuchEntityException(__('Product with SKU "%1" does not exist.', $sku));
            }
        );

        $this->scopeConfigMock->method('getValue')->willReturnCallback(
            function (string $path) {
                return match ($path) {
                    Config::XML_PATH_ENGRAVING_PRICE => '29.90',
                    Config::XML_PATH_ENGRAVING_LIMIT => '12',
                    Config::XML_PATH_ENGRAVING_FORBIDDEN_WORDS => 'droga,palavrao',
                    default => null,
                };
            }
        );
    }

    /**
     * Helper to create a valid configuration
     */
    private function createValidConfiguration(): ConfigurationInterface
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        return $config;
    }

    private function createSelection(string $group, string $component): SelectionInterface
    {
        $sel = $this->createMock(SelectionInterface::class);
        $sel->method('getGroup')->willReturn($group);
        $sel->method('getComponent')->willReturn($component);
        return $sel;
    }

    public function testValidConfigurationReturnsValidResult(): void
    {
        $config = $this->createValidConfiguration();
        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getMessages());
    }

    public function testMissingMandatoryGroupIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        // Missing solado
        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('O grupo obrigatório "Solado" não foi informado.', $result->getMessages());
    }

    public function testDuplicateSelectionForSameGroupIsRefused(): void
    {
        $config = $this->createValidConfiguration();
        $selections = $config->getSelections();
        // Add another cabedal
        $selections[] = $this->createSelection('cabedal', 'CAB-TELA-MENTA');
        $configMock = $this->createMock(ConfigurationInterface::class);
        $configMock->method('getSize')->willReturn(38);
        $configMock->method('getEngraving')->willReturn('LUIZ');
        $configMock->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $configMock);

        $this->assertFalse($result->isValid());
        $this->assertContains('O grupo "Cabedal" deve ter apenas uma seleção.', $result->getMessages());
    }

    public function testNonExistentComponentIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        $selections = [
            $this->createSelection('cabedal', 'CAB-OURO-24K'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('O componente "CAB-OURO-24K" não existe.', $result->getMessages());
    }

    public function testInactiveComponentIsRefused(): void
    {
        $inactiveComp = $this->createMock(ComponentInterface::class);
        $inactiveComp->method('getComponenteId')->willReturn(999);
        $inactiveComp->method('getGrupoId')->willReturn(1);
        $inactiveComp->method('getCodigo')->willReturn('CAB-INATIVO');
        $inactiveComp->method('getNome')->willReturn('Cabedal Inativo');
        $inactiveComp->method('isAtivo')->willReturn(false);
        $inactiveComp->method('getEstoque')->willReturn(10);
        $this->components['CAB-INATIVO'] = $inactiveComp;

        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        $selections = [
            $this->createSelection('cabedal', 'CAB-INATIVO'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('O componente "Cabedal Inativo" está inativo.', $result->getMessages());
    }

    public function testComponentInWrongGroupIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        // Solado component passed in cabedal group
        $selections = [
            $this->createSelection('cabedal', 'SOL-BORRACHA-MENTA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'O componente "Borracha Menta" não pertence ao grupo "Cabedal".',
            $result->getMessages()
        );
    }

    public function testComponentOutOfStockIsRefused(): void
    {
        $outOfStockComp = $this->createMock(ComponentInterface::class);
        $outOfStockComp->method('getComponenteId')->willReturn(888);
        $outOfStockComp->method('getGrupoId')->willReturn(1);
        $outOfStockComp->method('getCodigo')->willReturn('CAB-ZERADO');
        $outOfStockComp->method('getNome')->willReturn('Cabedal Zerado');
        $outOfStockComp->method('getAcabamento')->willReturn('trico');
        $outOfStockComp->method('isAtivo')->willReturn(true);
        $outOfStockComp->method('getEstoque')->willReturn(0);
        $this->components['CAB-ZERADO'] = $outOfStockComp;

        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        $selections = [
            $this->createSelection('cabedal', 'CAB-ZERADO'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('O componente "Cabedal Zerado" está fora de estoque.', $result->getMessages());
    }

    public function testSizeOutsideProductRangeIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(50); // Valid is 34-44
        $config->method('getEngraving')->willReturn('LUIZ');

        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('O tamanho 50 está fora da grade do produto (34 a 44).', $result->getMessages());
    }

    public function testEngravingOnProductThatDoesNotAllowIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(30); // Valid for Mini (26-33)
        $config->method('getEngraving')->willReturn('BIA');

        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ALGODAO-NUVEM'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-BORRACHA-MENTA'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-MINI', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('O produto "Nuvem Mini" não permite gravação.', $result->getMessages());
    }

    public function testEngravingWithHtmlIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('<script>alert(1)</script>');

        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'A gravação deve conter apenas letras, números, espaços e hífen.',
            $result->getMessages()
        );
    }

    public function testEngravingWithEmojiIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('NUVEM 👟');

        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'A gravação deve conter apenas letras, números, espaços e hífen.',
            $result->getMessages()
        );
    }

    public function testEngravingAboveLimitIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('NUVEMNUVEMNUVEM'); // 15 chars > 12

        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('A gravação não pode ultrapassar 12 caracteres.', $result->getMessages());
    }

    public function testEngravingWithForbiddenWordsIsRefused(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('SEM DROGA');

        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('A gravação contém palavras não permitidas.', $result->getMessages());
    }

    public function testRuleR01BloqueiaCamurcaComSoladoHolografico(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        // Camurca baunilha + Lua holografico
        $selections = [
            $this->createSelection('cabedal', 'CAB-CAMURCA-BAUNILHA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ALGODAO-NUVEM'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-LUA-HOLO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'A cola do solado holográfico mancha a camurça. Escolha outro solado ou outro cabedal.',
            $result->getMessages()
        );
    }

    public function testRuleR02ExigeCadarcoElasticoComCabedalTrico(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        // Cadarco elastico with Tela Menta (finish tela, not trico)
        $selections = [
            $this->createSelection('cabedal', 'CAB-TELA-MENTA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ELASTICO-MENTA'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-BORRACHA-MENTA'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'O cadarço elástico só segura em cabedal de tricô.',
            $result->getMessages()
        );
    }

    public function testRuleR03BloqueiaGlitterComVerniz(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        // Glitter + Verniz
        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-VERNIZ-ROSA'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ALGODAO-NUVEM'),
            $this->createSelection('emblema', 'EMB-NUVEM-GLITTER'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-BORRACHA-MENTA'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'Glitter com verniz pesa no visual. Fique com um dos dois.',
            $result->getMessages()
        );
    }

    public function testRuleR04ExigeCamaraDeArComSoladoTranslucido(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn(null);

        // Camara de ar with Borracha menta (not Sol translucido gelo)
        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ALGODAO-NUVEM'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-AR-VISIVEL'),
            $this->createSelection('solado', 'SOL-BORRACHA-MENTA'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'A câmara de ar só aparece com o solado translúcido.',
            $result->getMessages()
        );
    }

    public function testRuleR05BloqueiaCamaraDeArComGravacao(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(38);
        $config->method('getEngraving')->willReturn('LUIZ');

        // Camara de ar with engraving and Sol translucido
        $selections = [
            $this->createSelection('cabedal', 'CAB-TRICO-LAVANDA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ALGODAO-NUVEM'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-AR-VISIVEL'),
            $this->createSelection('solado', 'SOL-TRANSLUCIDO-GELO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains('Não dá para gravar sobre a câmara de ar.', $result->getMessages());
    }

    public function testReturnsAllErrorsInsteadOfOnlyFirst(): void
    {
        $config = $this->createMock(ConfigurationInterface::class);
        $config->method('getSize')->willReturn(50); // Error 1: Size outside range
        $config->method('getEngraving')->willReturn('<script>alert(1)</script>'); // Error 2: HTML

        // Camurca + Lua Holografico -> Error 3: Rule R01
        $selections = [
            $this->createSelection('cabedal', 'CAB-CAMURCA-BAUNILHA'),
            $this->createSelection('biqueira', 'BIQ-LISA-CEU'),
            $this->createSelection('calcanhar', 'CAL-ACOLCHOADO-CEU'),
            $this->createSelection('cadarco', 'CAD-ALGODAO-NUVEM'),
            $this->createSelection('emblema', 'EMB-NUVEM-PESSEGO'),
            $this->createSelection('entressola', 'ENT-EVA-NUVEM'),
            $this->createSelection('solado', 'SOL-LUA-HOLO'),
        ];
        $config->method('getSelections')->willReturn($selections);

        $result = $this->validator->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $messages = $result->getMessages();
        $this->assertGreaterThanOrEqual(3, count($messages));
        $this->assertContains('O tamanho 50 está fora da grade do produto (34 a 44).', $messages);
        $this->assertContains('A gravação deve conter apenas letras, números, espaços e hífen.', $messages);
        $this->assertContains(
            'A cola do solado holográfico mancha a camurça. Escolha outro solado ou outro cabedal.',
            $messages
        );
    }

    public function testUnknownSkuThrowsNoSuchEntityException(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Product with SKU "UNKNOWN-SKU" does not exist.');

        $config = $this->createValidConfiguration();
        $this->validator->validate('UNKNOWN-SKU', $config);
    }

    public function testNonAtelieSkuThrowsNoSuchEntityException(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->expectExceptionMessage('Product with SKU "SIMPLE-PRODUCT" is not an atelie product.');

        $config = $this->createValidConfiguration();
        $this->validator->validate('SIMPLE-PRODUCT', $config);
    }

    public function testDynamicAdminRuleIsAppliedImmediately(): void
    {
        // Add a new dynamic rule without deploy/code change
        $newRule = $this->createMock(RuleInterface::class);
        $newRule->method('getCodigo')->willReturn('R99');
        $newRule->method('getTipo')->willReturn('bloqueia');
        $newRule->method('getOrigemComponenteId')->willReturn(4); // CAB-TRICO-LAVANDA
        $newRule->method('getAlvoTipo')->willReturn('componente');
        $newRule->method('getAlvo')->willReturn('BIQ-LISA-CEU');
        $newRule->method('getMensagem')->willReturn('Regra dinâmica do admin: Tricô Lavanda não combina com Lisa Céu.');
        $newRule->method('isAtivo')->willReturn(true);

        $dynamicRules = array_merge($this->rules, [$newRule]);
        $ruleResultsMock = $this->createMock(RuleSearchResultsInterface::class);
        $ruleResultsMock->method('getItems')->willReturn($dynamicRules);

        // Reset ruleRepository getList mock with dynamic rules
        $ruleRepoMock = $this->createMock(RuleRepositoryInterface::class);
        $ruleRepoMock->method('getList')->willReturn($ruleResultsMock);

        $validatorWithDynamicRule = new ConfigurationValidator(
            $this->groupRepositoryMock,
            $this->componentRepositoryMock,
            $ruleRepoMock,
            $this->searchCriteriaBuilderMock,
            $this->sortOrderBuilderMock,
            $this->productRepositoryMock,
            $this->scopeConfigMock,
            null
        );

        $config = $this->createValidConfiguration(); // Has CAB-TRICO-LAVANDA and BIQ-LISA-CEU
        $result = $validatorWithDynamicRule->validate('NUVEM-RUNNER', $config);

        $this->assertFalse($result->isValid());
        $this->assertContains(
            'Regra dinâmica do admin: Tricô Lavanda não combina com Lisa Céu.',
            $result->getMessages()
        );
    }
}
