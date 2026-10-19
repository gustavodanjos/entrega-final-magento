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
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Normalizer;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\Config;
use Nuvem\Atelie\Model\Rule;
use Nuvem\Atelie\Model\ValidationResult;
use Nuvem\Atelie\Model\ValidationResultFactory;

/**
 * Configuration validator
 *
 * Single place in the system that decides whether an atelie sneaker can exist.
 */
class ConfigurationValidator implements ConfigurationValidatorInterface
{
    /**
     * Cache key for active rules
     */
    public const CACHE_KEY_ACTIVE_RULES = 'nuvem_atelie_active_rules';

    /**
     * Whitelist of supported rule types
     */
    private const ALLOWED_RULE_TYPES = [
        RuleInterface::TIPO_BLOQUEIA,
        RuleInterface::TIPO_EXIGE,
    ];

    /**
     * Whitelist of supported target types
     */
    private const ALLOWED_TARGET_TYPES = [
        RuleInterface::ALVO_TIPO_COMPONENTE,
        RuleInterface::ALVO_TIPO_ACABAMENTO,
        RuleInterface::ALVO_TIPO_GRAVACAO,
        'gravação',
    ];

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
     * Repository instance for retrieving component groups
     *
     * @var GroupRepositoryInterface
     */
    private GroupRepositoryInterface $_groupRepository;

    /**
     * Repository instance for retrieving atelie components
     *
     * @var ComponentRepositoryInterface
     */
    private ComponentRepositoryInterface $_componentRepository;

    /**
     * Repository instance for retrieving compatibility rules
     *
     * @var RuleRepositoryInterface
     */
    private RuleRepositoryInterface $_ruleRepository;

    /**
     * Builder instance for search criteria queries
     *
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $_searchCriteriaBuilder;

    /**
     * Builder instance for collection sort order
     *
     * @var SortOrderBuilder
     */
    private SortOrderBuilder $_sortOrderBuilder;

    /**
     * Catalog product repository instance
     *
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $_productRepository;

    /**
     * Store configuration reader
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $_scopeConfig;

    /**
     * Factory instance for creating validation results
     *
     * @var ValidationResultFactory|null
     */
    private ?ValidationResultFactory $_validationResultFactory;

    /**
     * Default cache frontend instance
     *
     * @var CacheInterface|null
     */
    private ?CacheInterface $_cache;

    /**
     * JSON serializer service instance
     *
     * @var SerializerInterface|null
     */
    private ?SerializerInterface $_serializer;

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
     * @param CacheInterface|null          $cache                   Cache frontend
     * @param SerializerInterface|null     $serializer              Serializer
     */
    public function __construct(
        GroupRepositoryInterface $groupRepository,
        ComponentRepositoryInterface $componentRepository,
        RuleRepositoryInterface $ruleRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SortOrderBuilder $sortOrderBuilder,
        ProductRepositoryInterface $productRepository,
        ScopeConfigInterface $scopeConfig,
        ?ValidationResultFactory $validationResultFactory = null,
        ?CacheInterface $cache = null,
        ?SerializerInterface $serializer = null
    ) {
        $this->_groupRepository = $groupRepository;
        $this->_componentRepository = $componentRepository;
        $this->_ruleRepository = $ruleRepository;
        $this->_searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->_sortOrderBuilder = $sortOrderBuilder;
        $this->_productRepository = $productRepository;
        $this->_scopeConfig = $scopeConfig;
        $this->_validationResultFactory = $validationResultFactory;
        $this->_cache = $cache;
        $this->_serializer = $serializer;
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
            $normalizedCode = $this->_normalizeString(
                (string) $group->getCodigo(),
                true
            );
            $activeGroupsByCode[$normalizedCode] = $group;
        }

        $selections = $configuration->getSelections() ?: [];
        $selectionsByGroup = [];
        $componentCodesToLoad = [];

        foreach ($selections as $selection) {
            $groupKey = $this->_normalizeString(
                (string) $selection->getGroup(),
                true
            );
            $selectionsByGroup[$groupKey][] = $selection;

            $compCode = $this->_normalizeString(
                (string) $selection->getComponent()
            );
            if ($compCode !== '') {
                $componentCodesToLoad[] = $compCode;
            }
        }

        foreach ($activeGroups as $group) {
            $groupCode = $this->_normalizeString(
                (string) $group->getCodigo(),
                true
            );
            $groupName = $group->getNome() ?: $group->getCodigo();

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

        // 2. Componente existe, está ativo e pertence ao grupo (batch prefetch)
        $loadedComponentsByCode = $this->_batchLoadComponents(
            $componentCodesToLoad
        );

        $validChosenComponents = [];
        foreach ($selections as $selection) {
            $rawGroup = (string) $selection->getGroup();
            $rawComp = (string) $selection->getComponent();
            $groupKey = $this->_normalizeString($rawGroup, true);
            $compKey = strtoupper($this->_normalizeString($rawComp));

            if (!isset($activeGroupsByCode[$groupKey])) {
                $messages[] = (string) __(
                    'O grupo "%1" não existe ou está inativo.',
                    $rawGroup
                );
                continue;
            }

            $group = $activeGroupsByCode[$groupKey];

            if (!isset($loadedComponentsByCode[$compKey])) {
                $messages[] = (string) __(
                    'O componente "%1" não existe.',
                    $rawComp
                );
                continue;
            }

            $component = $loadedComponentsByCode[$compKey];
            $compName = $component->getNome() ?: $rawComp;
            $grpName = $group->getNome() ?: $rawGroup;

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
                $validChosenComponents[$compKey] = $component;
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
        $engravingRaw = $configuration->getEngraving();
        $hasEngraving = ($engravingRaw !== null
            && trim((string) $engravingRaw) !== '');

        if ($hasEngraving && !$productData['permite_gravacao']) {
            $messages[] = (string) __(
                'O produto "%1" não permite gravação.',
                $productData['nome']
            );
        }

        // 6. Gravação válida (regex Unicode, limite e palavras proibidas)
        if ($engravingRaw !== null && (string) $engravingRaw !== '') {
            $this->_validateEngraving(
                (string) $engravingRaw,
                $messages
            );
        }

        // 7. Regras dinâmicas (bloqueia/exige: componente/acabamento/gravação)
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
     * Batch load components by code avoiding N+1 queries
     *
     * @param string[] $componentCodes List of component codes
     *
     * @return array<string, ComponentInterface>
     */
    private function _batchLoadComponents(array $componentCodes): array
    {
        $uniqueCodes = array_values(array_unique($componentCodes));
        if (empty($uniqueCodes)) {
            return [];
        }

        $compCriteria = $this->_searchCriteriaBuilder
            ->addFilter('codigo', $uniqueCodes, 'in')
            ->create();

        $loaded = $this->_componentRepository->getList($compCriteria);
        $items = $loaded->getItems() ?: [];

        $indexed = [];
        foreach ($items as $comp) {
            $norm = strtoupper($this->_normalizeString((string) $comp->getCodigo()));
            $indexed[$norm] = $comp;
        }

        // Fallback for mock environments where getList is not configured
        foreach ($uniqueCodes as $code) {
            $norm = strtoupper($this->_normalizeString($code));
            if (!isset($indexed[$norm])) {
                try {
                    $comp = $this->_componentRepository->getByCodigo($code);
                    $normCode = strtoupper(
                        $this->_normalizeString((string) $comp->getCodigo())
                    );
                    $indexed[$normCode] = $comp;
                } catch (NoSuchEntityException $e) {
                    $comp = null;
                }
            }
        }

        return $indexed;
    }

    /**
     * Validate engraving string against security and business constraints
     *
     * @param string $engravingRaw Raw engraving input
     * @param array  $messages     Messages array reference
     *
     * @return void
     */
    private function _validateEngraving(
        string $engravingRaw,
        array &$messages
    ): void {
        $engraving = $this->_normalizeString($engravingRaw);

        // Check for HTML injection or characters outside allowed Unicode set
        $hasHtml = ($engravingRaw !== strip_tags($engravingRaw))
            || preg_match('/[<>]/', $engravingRaw);

        // Possessive quantifier ++ avoids catastrophic backtracking (ReDoS safe)
        $matchesRegex = preg_match('/^[\p{L}\p{N}\s\-]++$/u', $engraving);

        if ($hasHtml || trim($engraving) === '' || !$matchesRegex) {
            $messages[] = (string) __(
                'A gravação deve conter apenas letras, números, espaços e hífen.'
            );
        }

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

        // Check forbidden words
        $forbiddenWordsVal = $this->_scopeConfig->getValue(
            Config::XML_PATH_ENGRAVING_FORBIDDEN_WORDS,
            ScopeInterface::SCOPE_STORE
        );
        if ($forbiddenWordsVal !== null
            && trim((string) $forbiddenWordsVal) !== ''
        ) {
            $normalizedEngravingLower = $this->_normalizeString($engraving, true);
            $words = preg_split('/[,\r\n]+/', (string) $forbiddenWordsVal);

            foreach ($words as $word) {
                $normalizedWord = $this->_normalizeString((string) $word, true);
                $hasWord = mb_stripos($normalizedEngravingLower, $normalizedWord);
                if ($normalizedWord !== '' && $hasWord !== false) {
                    $messages[] = (string) __(
                        'A gravação contém palavras não permitidas.'
                    );
                    break;
                }
            }
        }
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
     * Load active rules using tagged cache with automatic invalidation
     *
     * @return array
     */
    private function _loadActiveRules(): array
    {
        if ($this->_cache !== null && $this->_serializer !== null) {
            $cached = $this->_cache->load(self::CACHE_KEY_ACTIVE_RULES);
            if ($cached !== false && is_string($cached)) {
                try {
                    $unserialized = $this->_serializer->unserialize($cached);
                    if (is_array($unserialized)) {
                        return $unserialized;
                    }
                } catch (\Exception $e) {
                    $unserialized = null;
                }
            }
        }

        $ruleCriteria = $this->_searchCriteriaBuilder
            ->addFilter('ativo', 1)
            ->create();

        $rules = $this->_ruleRepository->getList($ruleCriteria)->getItems() ?: [];
        $rulesData = [];

        foreach ($rules as $rule) {
            $tipo = $rule->getTipo();
            $alvoTipo = $rule->getAlvoTipo();

            // Strict whitelist enforcement on data level
            if (!in_array($tipo, self::ALLOWED_RULE_TYPES, true)) {
                continue;
            }
            if (!in_array($alvoTipo, self::ALLOWED_TARGET_TYPES, true)) {
                continue;
            }

            $origemId = (int) $rule->getOrigemComponenteId();
            $origemCode = null;
            if ($rule instanceof DataObject) {
                $origemCode = $rule->getData('origem');
            }

            $rulesData[] = [
                'codigo' => (string) $rule->getCodigo(),
                'tipo' => (string) $tipo,
                'origem_componente_id' => $origemId,
                'origem' => $origemCode ? (string) $origemCode : null,
                'alvo_tipo' => (string) $alvoTipo,
                'alvo' => (string) $rule->getAlvo(),
                'mensagem' => (string) $rule->getMensagem(),
            ];
        }

        if ($this->_cache !== null && $this->_serializer !== null) {
            $this->_cache->save(
                $this->_serializer->serialize($rulesData),
                self::CACHE_KEY_ACTIVE_RULES,
                [Rule::CACHE_TAG],
                3600
            );
        }

        return $rulesData;
    }

    /**
     * Evaluate dynamic business rules from the database using safe operators
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
        $rules = $this->_loadActiveRules();

        $chosenComponentIds = [];
        $chosenComponentCodes = [];
        $chosenAcabamentos = [];

        foreach ($validChosenComponents as $comp) {
            if ($comp->getComponenteId()) {
                $chosenComponentIds[(int) $comp->getComponenteId()] = $comp;
            }
            $rawCompCode = (string) $comp->getCodigo();
            $codeNorm = strtoupper($this->_normalizeString($rawCompCode));
            $chosenComponentCodes[$codeNorm] = true;

            if ($comp->getAcabamento()) {
                $finishNorm = $this->_normalizeString(
                    (string) $comp->getAcabamento(),
                    true
                );
                $chosenAcabamentos[$finishNorm] = true;
            }
        }

        foreach ($rules as $rule) {
            $tipo = $rule['tipo'];
            $alvoTipo = $rule['alvo_tipo'];

            if (!in_array($tipo, self::ALLOWED_RULE_TYPES, true)
                || !in_array($alvoTipo, self::ALLOWED_TARGET_TYPES, true)
            ) {
                continue;
            }

            $origemId = (int) $rule['origem_componente_id'];
            $isOriginPresent = isset($chosenComponentIds[$origemId]);

            if (!$isOriginPresent && !empty($rule['origem'])) {
                $origemCodeNorm = strtoupper(
                    $this->_normalizeString((string) $rule['origem'])
                );
                if (isset($chosenComponentCodes[$origemCodeNorm])) {
                    $isOriginPresent = true;
                }
            }

            if (!$isOriginPresent) {
                continue;
            }

            $rawAlvo = (string) $rule['alvo'];
            $isTargetPresent = false;

            // Safe evaluation using whitelisted data operators (no eval/exec/SQL)
            if ($alvoTipo === RuleInterface::ALVO_TIPO_COMPONENTE) {
                $targetCode = strtoupper($this->_normalizeString($rawAlvo));
                $isTargetPresent = isset($chosenComponentCodes[$targetCode])
                    || (is_numeric($targetCode)
                        && isset($chosenComponentIds[(int) $targetCode]));
            } elseif ($alvoTipo === RuleInterface::ALVO_TIPO_ACABAMENTO) {
                $targetAcabamento = $this->_normalizeString($rawAlvo, true);
                $isTargetPresent = isset($chosenAcabamentos[$targetAcabamento]);
            } elseif ($alvoTipo === RuleInterface::ALVO_TIPO_GRAVACAO
                || $alvoTipo === 'gravação'
            ) {
                $isTargetPresent = $hasEngraving;
            }

            if ($tipo === RuleInterface::TIPO_BLOQUEIA && $isTargetPresent) {
                $messages[] = (string) __($rule['mensagem']);
            } elseif ($tipo === RuleInterface::TIPO_EXIGE && !$isTargetPresent) {
                $messages[] = (string) __($rule['mensagem']);
            }
        }
    }

    /**
     * Normalize string using Unicode NFC and optional lowercase
     *
     * @param string $value     Input string
     * @param bool   $lowercase Convert to lowercase
     *
     * @return string
     */
    private function _normalizeString(string $value, bool $lowercase = false): string
    {
        $trimmed = trim($value);
        if (class_exists(Normalizer::class)
            && !Normalizer::isNormalized($trimmed, Normalizer::FORM_C)
        ) {
            $normalized = Normalizer::normalize($trimmed, Normalizer::FORM_C);
            if ($normalized !== false) {
                $trimmed = $normalized;
            }
        }

        return $lowercase ? mb_strtolower($trimmed, 'UTF-8') : $trimmed;
    }
}
