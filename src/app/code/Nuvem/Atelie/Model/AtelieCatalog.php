<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;

/**
 * Class AtelieCatalog
 *
 * Real catalog service assembling groups, active components, and active rules.
 */
class AtelieCatalog implements AtelieCatalogInterface
{
    private const DEFAULT_PRODUCTS = [
        'NUVEM-RUNNER' => [
            'nome' => 'Nuvem Runner',
            'preco_base' => 449.90,
            'tamanho_min' => 34,
            'tamanho_max' => 44,
            'prazo_producao_dias' => 12,
            'permite_gravacao' => true,
        ],
        'NUVEM-MINI' => [
            'nome' => 'Nuvem Mini',
            'preco_base' => 329.90,
            'tamanho_min' => 26,
            'tamanho_max' => 33,
            'prazo_producao_dias' => 10,
            'permite_gravacao' => false,
        ],
    ];

    /**
     * @var GroupRepositoryInterface
     */
    private GroupRepositoryInterface $groupRepository;

    /**
     * @var ComponentRepositoryInterface
     */
    private ComponentRepositoryInterface $componentRepository;

    /**
     * @var RuleRepositoryInterface
     */
    private RuleRepositoryInterface $ruleRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @var SortOrderBuilder
     */
    private SortOrderBuilder $sortOrderBuilder;

    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param GroupRepositoryInterface $groupRepository
     * @param ComponentRepositoryInterface $componentRepository
     * @param RuleRepositoryInterface $ruleRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param ProductRepositoryInterface $productRepository
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        GroupRepositoryInterface $groupRepository,
        ComponentRepositoryInterface $componentRepository,
        RuleRepositoryInterface $ruleRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ProductRepositoryInterface $productRepository,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->groupRepository = $groupRepository;
        $this->componentRepository = $componentRepository;
        $this->ruleRepository = $ruleRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sortOrderBuilder = $sortOrderBuilder;
        $this->productRepository = $productRepository;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get the product data, the groups with their components and catalog rules of a SKU.
     *
     * @param string $sku
     * @return array
     * @throws NoSuchEntityException
     */
    public function getBySku(string $sku): array
    {
        $productData = $this->loadProductData($sku);
        [$grupos, $componentsById] = $this->loadGroupsAndComponents();
        $regras = $this->loadRules($componentsById);

        return [
            'sku' => $sku,
            'nome' => $productData['nome'],
            'preco_base' => $productData['preco_base'],
            'tamanho_min' => $productData['tamanho_min'],
            'tamanho_max' => $productData['tamanho_max'],
            'prazo_producao_dias' => $productData['prazo_producao_dias'],
            'gravacao' => $productData['gravacao'],
            'grupos' => $grupos,
            'regras' => $regras,
        ];
    }

    /**
     * Load product model data or fallback definitions
     *
     * @param string $sku
     * @return array
     * @throws NoSuchEntityException
     */
    private function loadProductData(string $sku): array
    {
        $default = self::DEFAULT_PRODUCTS[$sku] ?? null;

        try {
            $product = $this->productRepository->get($sku);
            $nome = $product->getName();
            $precoBase = (float) $product->getPrice();

            $minAttr = $product->getCustomAttribute('atelie_tamanho_min');
            $maxAttr = $product->getCustomAttribute('atelie_tamanho_max');
            $prazoAttr = $product->getCustomAttribute('atelie_prazo_producao');
            $gravacaoAttr = $product->getCustomAttribute('atelie_permite_gravacao');

            $tamanhoMin = $minAttr !== null
                ? (int) $minAttr->getValue()
                : ($default['tamanho_min'] ?? 34);
            $tamanhoMax = $maxAttr !== null
                ? (int) $maxAttr->getValue()
                : ($default['tamanho_max'] ?? 44);
            $prazo = $prazoAttr !== null
                ? (int) $prazoAttr->getValue()
                : ($default['prazo_producao_dias'] ?? 12);
            $permiteGravacao = $gravacaoAttr !== null
                ? (bool) $gravacaoAttr->getValue()
                : ($default['permite_gravacao'] ?? false);
        } catch (NoSuchEntityException $e) {
            if ($default === null) {
                throw new NoSuchEntityException(
                    __('Product with SKU "%1" does not exist.', $sku)
                );
            }

            $nome = $default['nome'];
            $precoBase = (float) $default['preco_base'];
            $tamanhoMin = (int) $default['tamanho_min'];
            $tamanhoMax = (int) $default['tamanho_max'];
            $prazo = (int) $default['prazo_producao_dias'];
            $permiteGravacao = (bool) $default['permite_gravacao'];
        }

        $gravacao = null;
        if ($permiteGravacao) {
            $precoCfg = $this->scopeConfig->getValue(
                Config::XML_PATH_ENGRAVING_PRICE,
                ScopeInterface::SCOPE_STORE
            );
            $limiteCfg = $this->scopeConfig->getValue(
                Config::XML_PATH_ENGRAVING_LIMIT,
                ScopeInterface::SCOPE_STORE
            );

            $gravacao = [
                'preco' => $precoCfg !== null ? (float) $precoCfg : 29.90,
                'limite' => $limiteCfg !== null ? (int) $limiteCfg : 12,
            ];
        }

        return [
            'nome' => $nome,
            'preco_base' => $precoBase,
            'tamanho_min' => $tamanhoMin,
            'tamanho_max' => $tamanhoMax,
            'prazo_producao_dias' => $prazo,
            'gravacao' => $gravacao,
        ];
    }

    /**
     * Load ordered active groups with their active components
     *
     * @return array
     */
    private function loadGroupsAndComponents(): array
    {
        $sortOrder = $this->sortOrderBuilder
            ->setField('ordem')
            ->setAscendingDirection()
            ->create();

        $groupCriteria = $this->searchCriteriaBuilder
            ->addFilter('ativo', 1)
            ->addSortOrder($sortOrder)
            ->create();

        $groupList = $this->groupRepository->getList($groupCriteria);

        $componentCriteria = $this->searchCriteriaBuilder
            ->addFilter('ativo', 1)
            ->addSortOrder($sortOrder)
            ->create();

        $allComponents = $this->componentRepository->getList($componentCriteria)->getItems();

        $componentsByGroup = [];
        $componentsById = [];
        foreach ($allComponents as $comp) {
            $componentsById[$comp->getComponenteId()] = $comp->getCodigo();
            $componentsByGroup[$comp->getGrupoId()][] = [
                'codigo' => $comp->getCodigo(),
                'nome' => $comp->getNome(),
                'cor_hex' => $comp->getCorHex(),
                'acabamento' => $comp->getAcabamento(),
                'preco_adicional' => (float) $comp->getPrecoAdicional(),
                'padrao' => (bool) $comp->isPadrao(),
            ];
        }

        $gruposData = [];
        foreach ($groupList->getItems() as $group) {
            $gruposData[] = [
                'codigo' => $group->getCodigo(),
                'nome' => $group->getNome(),
                'ordem' => (int) $group->getOrdem(),
                'camada_svg' => $group->getCamadaSvg(),
                'componentes' => $componentsByGroup[$group->getGrupoId()] ?? [],
            ];
        }

        return [$gruposData, $componentsById];
    }

    /**
     * Load active rules with component code as origin
     *
     * @param array $componentsById
     * @return array
     */
    private function loadRules(array $componentsById): array
    {
        $ruleCriteria = $this->searchCriteriaBuilder
            ->addFilter('ativo', 1)
            ->create();

        $ruleList = $this->ruleRepository->getList($ruleCriteria);

        $regrasData = [];
        foreach ($ruleList->getItems() as $rule) {
            $origemId = (int) $rule->getOrigemComponenteId();
            $origemCodigo = $componentsById[$origemId] ?? null;

            if (!$origemCodigo) {
                try {
                    $origemComp = $this->componentRepository->getById($origemId);
                    $origemCodigo = $origemComp->getCodigo();
                } catch (\Exception $e) {
                    continue;
                }
            }

            $regrasData[] = [
                'codigo' => $rule->getCodigo(),
                'tipo' => $rule->getTipo(),
                'origem' => $origemCodigo,
                'alvo_tipo' => $rule->getAlvoTipo(),
                'alvo' => $rule->getAlvo(),
                'mensagem' => $rule->getMensagem(),
            ];
        }

        return $regrasData;
    }
}
