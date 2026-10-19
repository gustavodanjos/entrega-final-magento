<?php
/**
 * Configuration validator
 *
 * PHP version 8.2
 *
 * @category Nuvem
 * @package  Nuvem_Atelie
 * @author   Nuvem Team <dev@nuvem.test>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/gustavodanjos/entrega-final-magento
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\Config;
use Nuvem\Atelie\Model\ValidationResult;
use Nuvem\Atelie\Model\ValidationResultFactory;

/**
 * Class ConfigurationValidator
 *
 * Single place in the system that decides whether an atelie sneaker can exist.
 *
 * @category Nuvem
 * @package  Nuvem_Atelie
 * @author   Nuvem Team <dev@nuvem.test>
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     https://github.com/gustavodanjos/entrega-final-magento
 */
class ConfigurationValidator implements ConfigurationValidatorInterface
{
    /**
     * Default products specifications
     */
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
     * Group repository
     *
     * @var GroupRepositoryInterface
     */
    private GroupRepositoryInterface $_groupRepository;

    /**
     * Component repository
     *
     * @var ComponentRepositoryInterface
     */
    private ComponentRepositoryInterface $_componentRepository;

    /**
     * Rule repository
     *
     * @var RuleRepositoryInterface
     */
    private RuleRepositoryInterface $_ruleRepository;

    /**
     * Search criteria builder
     *
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $_searchCriteriaBuilder;

    /**
     * Sort order builder
     *
     * @var SortOrderBuilder
     */
    private SortOrderBuilder $_sortOrderBuilder;

    /**
     * Product repository
     *
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $_productRepository;

    /**
     * Scope config
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $_scopeConfig;

    /**
     * Validation result factory
     *
     * @var ValidationResultFactory|null
     */
    private ?ValidationResultFactory $_validationResultFactory;

    /**
     * Constructor
     *
     * @param GroupRepositoryInterface     $groupRepository         Group repo
     * @param ComponentRepositoryInterface $componentRepository     Comp repo
     * @param RuleRepositoryInterface      $ruleRepository          Rule repo
     * @param SearchCriteriaBuilder        $searchCriteriaBuilder   Criteria
     * @param SortOrderBuilder             $sortOrderBuilder        Sort order
     * @param ProductRepositoryInterface   $productRepository       Product repo
     * @param ScopeConfigInterface         $scopeConfig             Scope config
     * @param ValidationResultFactory|null $validationResultFactory Result factory
     */
    public function __construct(
        GroupRepositoryInterface $groupRepository,
        ComponentRepositoryInterface $componentRepository,
        RuleRepositoryInterface $ruleRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ProductRepositoryInterface $productRepository,
        ScopeConfigInterface $scopeConfig,
        ?ValidationResultFactory $validationResultFactory = null
    ) {
        $this->_groupRepository = $groupRepository;
        $this->_componentRepository = $componentRepository;
        $this->_ruleRepository = $ruleRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_sortOrderBuilder = $sortOrderBuilder;
        $this->_productRepository = $productRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->_validationResultFactory = $validationResultFactory;
    }

    /**
     * Validate a configuration for the given SKU.
     *
     * @param string                 $sku           Target product SKU
     * @param ConfigurationInterface $configuration Configuration object
     *
     * @return ValidationResultInterface
     *
     * @throws NoSuchEntityException
     */
    public function validate(
        string $sku,
        ConfigurationInterface $configuration
    ): ValidationResultInterface {
        $productData = $this->_loadProductData($sku);
        $messages = [];

        // 1. Grupos completos
        $activeGroups = $this->_loadActiveGroups();
        $activeGroupsByCode = [];
        foreach ($activeGroups as $group) {
            $activeGroupsByCode[$group->getCodigo()] = $group;
        }

        $selections = $configuration->getSelections() ?: [];
        $selectionsByGroup = [];
        foreach ($selections as $selection) {
            $selectionsByGroup[$selection->getGroup()][] = $selection;
        }

        foreach ($activeGroups as $group) {
            $groupCode = $group->getCodigo();
            $groupName = $group->getNome() ?: $groupCode;
            if (!isset($selectionsByGroup[$groupCode])
                || empty($selectionsByGroup[$groupCode])
            ) {
                $messages[] = (string) __(
                    'O grupo obrigatório "%1" não foi informado.',
                    $groupName
                );
            } elseif (count($selectionsByGroup[$groupCode]) > 1) {
                $messages[] = (string) __(
                    'O grupo "%1" deve ter apenas uma seleção.',
                    $groupName
                );
            }
        }

        // 2. Componente existe, está ativo e pertence ao grupo
        $validChosenComponents = [];
        foreach ($selections as $selection) {
            $groupCode = $selection->getGroup();
            $componentCode = $selection->getComponent();

            if (!isset($activeGroupsByCode[$groupCode])) {
                $messages[] = (string) __(
                    'O grupo "%1" não existe ou está inativo.',
                    $groupCode
                );
                continue;
            }

            $group = $activeGroupsByCode[$groupCode];

            try {
                $component = $this->_componentRepository->getByCodigo(
                    $componentCode
                );
            } catch (NoSuchEntityException $e) {
                $messages[] = (string) __(
                    'O componente "%1" não existe.',
                    $componentCode
                );
                continue;
            }

            $compName = $component->getNome() ?: $componentCode;
            $grpName = $group->getNome() ?: $groupCode;

            if (!$component->isAtivo()) {
                $messages[] = (string) __(
                    'O componente "%1" está inativo.',
                    $compName
                );
            }

            if ((int) $component->getGrupoId() !== (int) $group->getGrupoId()) {
                $messages[] = (string) __(
                    'O componente "%1" não pertence ao grupo "%2".',
                    $compName,
                    $grpName
                );
            } else {
                $validChosenComponents[$componentCode] = $component;
            }
        }

        // 3. Tem estoque
        foreach ($validChosenComponents as $component) {
            if ((int) $component->getEstoque() <= 0) {
                $messages[] = (string) __(
                    'O componente "%1" está fora de estoque.',
                    $component->getNome() ?: $component->getCodigo()
                );
            }
        }

        // 4. Tamanho dentro da grade do produto (atributo EAV)
        $size = (int) $configuration->getSize();
        if ($size < $productData['tamanho_min']
            || $size > $productData['tamanho_max']
        ) {
            $messages[] = (string) __(
                'O tamanho %1 está fora da grade do produto (%2 a %3).',
                $size,
                $productData['tamanho_min'],
                $productData['tamanho_max']
            );
        }

        // 5. Gravação permitida pelo produto
        $engraving = $configuration->getEngraving();
        $hasEngraving = ($engraving !== null && trim($engraving) !== '');

        if ($hasEngraving && !$productData['permite_gravacao']) {
            $messages[] = (string) __(
                'O produto "%1" não permite gravação.',
                $productData['nome']
            );
        }

        // 6. Gravação válida (regex Unicode, limite e palavras proibidas)
        if ($engraving !== null && $engraving !== '') {
            $limitVal = $this->_scopeConfig->getValue(
                Config::XML_PATH_ENGRAVING_LIMIT,
                ScopeInterface::SCOPE_STORE
            );
            $limit = ($limitVal !== null && $limitVal !== '') ? (int) $limitVal : 12;

            if (mb_strlen($engraving, 'UTF-8') > $limit) {
                $messages[] = (string) __(
                    'A gravação não pode ultrapassar %1 caracteres.',
                    $limit
                );
            }

            if (trim($engraving) === ''
                || !preg_match('/^[\p{L}\p{N}\s\-]+$/u', $engraving)
            ) {
                $messages[] = (string) __(
                    'A gravação deve conter apenas letras, números, espaços e hífen.'
                );
            }

            $forbiddenWordsVal = $this->_scopeConfig->getValue(
                Config::XML_PATH_ENGRAVING_FORBIDDEN_WORDS,
                ScopeInterface::SCOPE_STORE
            );
            if ($forbiddenWordsVal !== null
                && trim((string) $forbiddenWordsVal) !== ''
            ) {
                $words = preg_split('/[,\r\n]+/', (string) $forbiddenWordsVal);
                foreach ($words as $word) {
                    $word = trim($word);
                    if ($word !== '' && mb_stripos($engraving, $word) !== false) {
                        $messages[] = (string) __(
                            'A gravação contém palavras não permitidas.'
                        );
                        break;
                    }
                }
            }
        }

        // 7. As regras (bloqueia/exige com alvo componente, acabamento ou gravação)
        $this->_evaluateRules($validChosenComponents, $hasEngraving, $messages);

        $messages = array_values(array_unique($messages));
        $isValid = empty($messages);

        if ($this->_validationResultFactory !== null) {
            $resultPayload = [
                'valid' => $isValid,
                'messages' => $messages,
            ];
            return $this->_validationResultFactory->create($resultPayload);
        }

        return new ValidationResult($isValid, $messages);
    }

    /**
     * Load product data for validation
     *
     * @param string $sku Product SKU
     *
     * @return array
     *
     * @throws NoSuchEntityException
     */
    private function _loadProductData(string $sku): array
    {
        $default = self::DEFAULT_PRODUCTS[$sku] ?? null;

        try {
            $product = $this->_productRepository->get($sku);

            $isAtelie = $product->getTypeId() === 'atelie'
                || $default !== null
                || $product->getCustomAttribute('atelie_tamanho_min') !== null;

            if (!$isAtelie) {
                throw new NoSuchEntityException(
                    __('Product with SKU "%1" is not an atelie product.', $sku)
                );
            }

            $productName = $product->getName() ?: ($default['nome'] ?? $sku);

            $minAttr = $product->getCustomAttribute('atelie_tamanho_min');
            $maxAttr = $product->getCustomAttribute('atelie_tamanho_max');
            $gravAttr = $product->getCustomAttribute('atelie_permite_gravacao');

            $minVal = $minAttr?->getValue();
            $tamanhoMin = ($minVal !== null && $minVal !== '')
                ? (int) $minVal
                : ($default['tamanho_min'] ?? 34);

            $maxVal = $maxAttr?->getValue();
            $tamanhoMax = ($maxVal !== null && $maxVal !== '')
                ? (int) $maxVal
                : ($default['tamanho_max'] ?? 44);

            $gravVal = $gravAttr?->getValue();
            $permiteGravacao = ($gravVal !== null && $gravVal !== '')
                ? (bool) $gravVal
                : ($default['permite_gravacao'] ?? false);
        } catch (NoSuchEntityException $e) {
            if ($default === null) {
                throw $e;
            }

            $productName = $default['nome'];
            $tamanhoMin = (int) $default['tamanho_min'];
            $tamanhoMax = (int) $default['tamanho_max'];
            $permiteGravacao = (bool) $default['permite_gravacao'];
        }

        return [
            'nome' => $productName,
            'tamanho_min' => $tamanhoMin,
            'tamanho_max' => $tamanhoMax,
            'permite_gravacao' => $permiteGravacao,
        ];
    }

    /**
     * Load active groups ordered by display sequence
     *
     * @return array
     */
    private function _loadActiveGroups(): array
    {
        $sortOrder = $this->_sortOrderBuilder
            ->setField('ordem')
            ->setAscendingDirection()
            ->create();

        $groupCriteria = $this->_searchCriteriaBuilder
            ->addFilter('ativo', 1)
            ->addSortOrder($sortOrder)
            ->create();

        return $this->_groupRepository->getList($groupCriteria)->getItems() ?: [];
    }

    /**
     * Evaluate dynamic business rules from the database
     *
     * @param ComponentInterface[] $validChosenComponents Valid components list
     * @param bool                 $hasEngraving          Has valid engraving
     * @param array                $messages              Messages list reference
     *
     * @return void
     */
    private function _evaluateRules(
        array $validChosenComponents,
        bool $hasEngraving,
        array &$messages
    ): void {
        $ruleCriteria = $this->_searchCriteriaBuilder
            ->addFilter('ativo', 1)
            ->create();

        $rules = $this->_ruleRepository->getList($ruleCriteria)->getItems() ?: [];

        $chosenComponentIds = [];
        $chosenComponentCodes = [];
        $chosenAcabamentos = [];

        foreach ($validChosenComponents as $comp) {
            if ($comp->getComponenteId()) {
                $chosenComponentIds[(int) $comp->getComponenteId()] = $comp;
            }
            $chosenComponentCodes[strtoupper((string) $comp->getCodigo())] = true;
            if ($comp->getAcabamento()) {
                $acabamentoKey = strtolower((string) $comp->getAcabamento());
                $chosenAcabamentos[$acabamentoKey] = true;
            }
        }

        foreach ($rules as $rule) {
            $origemId = (int) $rule->getOrigemComponenteId();
            $isOriginPresent = isset($chosenComponentIds[$origemId]);

            if (!$isOriginPresent && $rule instanceof DataObject) {
                $origemCode = $rule->getData('origem');
                if ($origemCode
                    && isset($chosenComponentCodes[strtoupper((string) $origemCode)])
                ) {
                    $isOriginPresent = true;
                }
            }

            if (!$isOriginPresent) {
                continue;
            }

            $tipo = strtolower(trim((string) $rule->getTipo()));
            $alvoTipo = strtolower(trim((string) $rule->getAlvoTipo()));
            $alvo = trim((string) $rule->getAlvo());

            $isTargetPresent = false;
            if ($alvoTipo === RuleInterface::ALVO_TIPO_COMPONENTE) {
                $targetCode = strtoupper($alvo);
                $isTargetPresent = isset($chosenComponentCodes[$targetCode])
                    || (is_numeric($alvo)
                        && isset($chosenComponentIds[(int) $alvo]));
            } elseif ($alvoTipo === RuleInterface::ALVO_TIPO_ACABAMENTO) {
                $targetAcabamento = strtolower($alvo);
                $isTargetPresent = isset($chosenAcabamentos[$targetAcabamento]);
            } elseif ($alvoTipo === RuleInterface::ALVO_TIPO_GRAVACAO
                || $alvoTipo === 'gravação'
            ) {
                $isTargetPresent = $hasEngraving;
            }

            if ($tipo === RuleInterface::TIPO_BLOQUEIA && $isTargetPresent) {
                $messages[] = (string) __($rule->getMensagem());
            } elseif ($tipo === RuleInterface::TIPO_EXIGE && !$isTargetPresent) {
                $messages[] = (string) __($rule->getMensagem());
            }
        }
    }
}
