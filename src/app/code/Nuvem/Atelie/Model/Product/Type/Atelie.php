<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Product\Type;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Option as CatalogProductOption;
use Magento\Catalog\Model\Product\Type as CatalogProductType;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\MediaStorage\Helper\File\Storage\Database as FileStorageDb;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\SelectionInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\ConfigurationFactory;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Selection;
use Nuvem\Atelie\Model\SelectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Atelie product type model.
 *
 * Implements customizable sneaker product logic: validation via ConfigurationValidatorInterface,
 * cart line consolidation via ConfigurationSerializer, and component-based inventory availability.
 */
class Atelie extends AbstractType
{
    /**
     * Product type code
     */
    public const TYPE_CODE = 'atelie';

    /**
     * Custom option code for serialized configuration
     */
    public const CUSTOM_OPTION_CONFIGURACAO = 'atelie_configuracao';

    /**
     * Product can be configured
     *
     * @var bool
     */
    protected $_canConfigure = true;

    /**
     * Product is not composite
     *
     * @var bool
     */
    protected $_isComposite = false;

    /**
     * Configuration validator instance
     *
     * @var ConfigurationValidatorInterface
     */
    private ConfigurationValidatorInterface $configurationValidator;

    /**
     * Configuration serializer instance
     *
     * @var ConfigurationSerializer
     */
    private ConfigurationSerializer $configurationSerializer;

    /**
     * Group repository instance
     *
     * @var GroupRepositoryInterface
     */
    private GroupRepositoryInterface $groupRepository;

    /**
     * Component repository instance
     *
     * @var ComponentRepositoryInterface
     */
    private ComponentRepositoryInterface $componentRepository;

    /**
     * Search criteria builder instance
     *
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * Factory instance for creating configurations
     *
     * @var ConfigurationFactory|null
     */
    private ?ConfigurationFactory $configurationFactory;

    /**
     * Factory instance for creating selections
     *
     * @var SelectionFactory|null
     */
    private ?SelectionFactory $selectionFactory;

    /**
     * Cached group labels
     *
     * @var array<string, string>
     */
    private array $groupLabels = [];

    /**
     * Cached component labels
     *
     * @var array<string, string>
     */
    private array $componentLabels = [];

    /**
     * @param CatalogProductOption $catalogProductOption
     * @param EavConfig $eavConfig
     * @param CatalogProductType $catalogProductType
     * @param EventManagerInterface $eventManager
     * @param FileStorageDb $fileStorageDb
     * @param Filesystem $filesystem
     * @param Registry $coreRegistry
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param ConfigurationValidatorInterface $configurationValidator
     * @param ConfigurationSerializer $configurationSerializer
     * @param GroupRepositoryInterface $groupRepository
     * @param ComponentRepositoryInterface $componentRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param JsonSerializer|null $serializer
     * @param UploaderFactory|null $uploaderFactory
     * @param ConfigurationFactory|null $configurationFactory
     * @param SelectionFactory|null $selectionFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        CatalogProductOption $catalogProductOption,
        EavConfig $eavConfig,
        CatalogProductType $catalogProductType,
        EventManagerInterface $eventManager,
        FileStorageDb $fileStorageDb,
        Filesystem $filesystem,
        Registry $coreRegistry,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        ConfigurationValidatorInterface $configurationValidator,
        ConfigurationSerializer $configurationSerializer,
        GroupRepositoryInterface $groupRepository,
        ComponentRepositoryInterface $componentRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ?JsonSerializer $serializer = null,
        ?UploaderFactory $uploaderFactory = null,
        ?ConfigurationFactory $configurationFactory = null,
        ?SelectionFactory $selectionFactory = null
    ) {
        parent::__construct(
            $catalogProductOption,
            $eavConfig,
            $catalogProductType,
            $eventManager,
            $fileStorageDb,
            $filesystem,
            $coreRegistry,
            $logger,
            $productRepository,
            $serializer,
            $uploaderFactory
        );
        $this->configurationValidator = $configurationValidator;
        $this->configurationSerializer = $configurationSerializer;
        $this->groupRepository = $groupRepository;
        $this->componentRepository = $componentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->configurationFactory = $configurationFactory;
        $this->selectionFactory = $selectionFactory;
    }

    /**
     * Check if product can be bought from list
     *
     * Atelie products require customized options and cannot be bought directly from listings.
     *
     * @param Product $product
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isPossibleBuyFromList($product): bool
    {
        return false;
    }

    /**
     * Check if product has options
     *
     * @param Product $product
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hasOptions($product): bool
    {
        return true;
    }

    /**
     * Check if product has required options
     *
     * @param Product $product
     * @return bool
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hasRequiredOptions($product): bool
    {
        return true;
    }

    /**
     * Check if product can be configured
     *
     * @param Product $product
     * @return bool
     */
    public function canConfigure($product): bool
    {
        if ($product instanceof Product) {
            return $product->isAvailable() && $this->_canConfigure;
        }

        return $this->_canConfigure;
    }

    /**
     * Check if product is available for sale.
     *
     * An atelie product is salable only if every active group has at least one component available in stock.
     *
     * @param Product $product
     * @return bool
     */
    public function isSalable($product): bool
    {
        $status = $product->getStatus();
        if ($status !== null && (int) $status !== Status::STATUS_ENABLED) {
            return false;
        }

        if ($product->hasData('all_items_salable')) {
            return (bool) $product->getData('all_items_salable');
        }

        $salable = $this->checkGroupsAvailability();
        $product->setData('all_items_salable', $salable);
        $product->setData('is_salable', $salable ? 1 : 0);

        return $salable;
    }

    /**
     * Prepare product and its configuration to be added to cart.
     *
     * Reads atelie from buyRequest, validates with ConfigurationValidatorInterface,
     * returns error message in strict mode, and records normalized custom option atelie_configuracao.
     *
     * @param DataObject $buyRequest
     * @param Product $product
     * @param string $processMode
     * @return array|string
     */
    protected function _prepareProduct(DataObject $buyRequest, $product, $processMode)
    {
        $configuration = $this->extractConfiguration($buyRequest);

        if ($configuration === null) {
            if ($this->_isStrictProcessMode($processMode)) {
                return (string) $this->getSpecifyOptionMessage();
            }

            return parent::_prepareProduct($buyRequest, $product, $processMode);
        }

        $sku = (string) ($product->getSku() ?: $product->getData('sku'));
        try {
            $validationResult = $this->configurationValidator->validate($sku, $configuration);
        } catch (NoSuchEntityException|LocalizedException $e) {
            if ($this->_isStrictProcessMode($processMode)) {
                return $e->getMessage();
            }
            $validationResult = null;
        }

        if ($validationResult !== null && !$validationResult->isValid()) {
            if ($this->_isStrictProcessMode($processMode)) {
                $messages = $validationResult->getMessages();
                return !empty($messages) ? implode("\n", $messages) : (string) $this->getSpecifyOptionMessage();
            }
        }

        try {
            $serialized = $this->configurationSerializer->serialize($configuration);
        } catch (\Exception $e) {
            if ($this->_isStrictProcessMode($processMode)) {
                return $e->getMessage();
            }
            $serialized = json_encode([
                'tamanho' => $configuration->getSize(),
                'gravacao' => $configuration->getEngraving(),
                'selecoes' => [],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $result = parent::_prepareProduct($buyRequest, $product, $processMode);
        if (is_string($result)) {
            return $result;
        }

        $product->addCustomOption(self::CUSTOM_OPTION_CONFIGURACAO, $serialized);

        return $result;
    }

    /**
     * Process buyRequest options for atelie product type.
     *
     * @param Product $product
     * @param DataObject $buyRequest
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function processBuyRequest($product, $buyRequest): array
    {
        $options = [];
        $atelie = $buyRequest->getData('atelie') ?? $buyRequest->getData(self::CUSTOM_OPTION_CONFIGURACAO);
        if ($atelie !== null) {
            $options['atelie'] = $atelie;
        }

        return $options;
    }

    /**
     * Retrieve order options array with configuration and readable label/value list.
     *
     * @param Product $product
     * @return array
     */
    public function getOrderOptions($product): array
    {
        $options = parent::getOrderOptions($product);

        $customOption = $product->getCustomOption(self::CUSTOM_OPTION_CONFIGURACAO);
        $configData = null;

        if ($customOption) {
            $val = $customOption->getValue();
            if (is_string($val)) {
                $configData = json_decode($val, true);
            } elseif (is_array($val)) {
                $configData = $val;
            }
        }

        if ($configData === null) {
            $info = $product->getCustomOption('info_buyRequest');
            if ($info) {
                $buyRequestData = $this->serializer->unserialize($info->getValue());
                if (is_array($buyRequestData)) {
                    $atelie = $buyRequestData['atelie']
                        ?? $buyRequestData[self::CUSTOM_OPTION_CONFIGURACAO]
                        ?? null;
                    if (is_string($atelie)) {
                        $configData = json_decode($atelie, true);
                    } elseif (is_array($atelie)) {
                        $configData = $atelie;
                    }
                }
            }
        }

        if (is_array($configData)) {
            $options[self::CUSTOM_OPTION_CONFIGURACAO] = $configData;
            $options['configuracao'] = $configData;

            $readableOptions = $this->buildReadableOptions($configData);
            if (!empty($readableOptions)) {
                $options['attributes_info'] = $readableOptions;
                $options['additional_options'] = $readableOptions;
            }
        }

        return $options;
    }

    /**
     * Delete type specific data
     *
     * @param Product $product
     * @return $this
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function deleteTypeSpecificData(Product $product): static
    {
        return $this;
    }

    /**
     * Extract or construct configuration object from buy request.
     *
     * @param DataObject $buyRequest
     * @return ConfigurationInterface|null
     */
    private function extractConfiguration(DataObject $buyRequest): ?ConfigurationInterface
    {
        $atelieData = $buyRequest->getData('atelie') ?? $buyRequest->getData(self::CUSTOM_OPTION_CONFIGURACAO);
        if ($atelieData === null) {
            return null;
        }

        if ($atelieData instanceof ConfigurationInterface) {
            return $atelieData;
        }

        if ($atelieData instanceof DataObject) {
            $atelieData = $atelieData->getData();
        }

        if (is_string($atelieData)) {
            $decoded = json_decode($atelieData, true);
            if (is_array($decoded)) {
                $atelieData = $decoded;
            }
        }

        if (!is_array($atelieData)) {
            return null;
        }

        $size = (int) ($atelieData['tamanho'] ?? $atelieData['size'] ?? 0);
        $engraving = $atelieData['gravacao'] ?? $atelieData['engraving'] ?? null;
        if ($engraving !== null) {
            $engraving = (string) $engraving;
        }

        $rawSelections = $atelieData['selecoes'] ?? $atelieData['selections'] ?? null;
        $selections = [];

        if (is_array($rawSelections)) {
            foreach ($rawSelections as $key => $item) {
                if ($item instanceof SelectionInterface) {
                    $selections[] = $item;
                } elseif (is_array($item)) {
                    $group = (string) ($item['grupo'] ?? $item['group'] ?? (is_string($key) ? $key : ''));
                    $component = (string) ($item['componente'] ?? $item['component'] ?? '');
                    if ($group !== '' && $component !== '') {
                        $selections[] = $this->createSelection($group, $component);
                    }
                } elseif (is_string($item) && is_string($key)) {
                    $selections[] = $this->createSelection($key, $item);
                }
            }
        } else {
            $ignoredKeys = [
                'tamanho',
                'size',
                'gravacao',
                'engraving',
                'qty',
                'uenc',
                'form_key',
                'reset_cart',
            ];
            foreach ($atelieData as $key => $value) {
                if (!in_array($key, $ignoredKeys, true) && is_string($value) && $value !== '') {
                    $selections[] = $this->createSelection((string) $key, $value);
                }
            }
        }

        return $this->createConfiguration($size, $engraving, $selections);
    }

    /**
     * Create Selection instance
     *
     * @param string $group
     * @param string $component
     * @return SelectionInterface
     */
    private function createSelection(string $group, string $component): SelectionInterface
    {
        if ($this->selectionFactory !== null) {
            return $this->selectionFactory->create([
                'group' => $group,
                'component' => $component,
            ]);
        }

        return new Selection($group, $component);
    }

    /**
     * Create Configuration instance
     *
     * @param int $size
     * @param string|null $engraving
     * @param SelectionInterface[] $selections
     * @return ConfigurationInterface
     */
    private function createConfiguration(int $size, ?string $engraving, array $selections): ConfigurationInterface
    {
        if ($this->configurationFactory !== null) {
            return $this->configurationFactory->create([
                'size' => $size,
                'engraving' => $engraving,
                'selections' => $selections,
            ]);
        }

        return new Configuration($size, $engraving, $selections);
    }

    /**
     * Check if all active groups have at least one component available in stock.
     *
     * @return bool
     */
    private function checkGroupsAvailability(): bool
    {
        try {
            $groupCriteria = $this->searchCriteriaBuilder
                ->addFilter('ativo', 1)
                ->create();
            $groups = $this->groupRepository->getList($groupCriteria)->getItems();

            if (empty($groups)) {
                return false;
            }

            $compCriteria = $this->searchCriteriaBuilder
                ->addFilter('ativo', 1)
                ->create();
            $components = $this->componentRepository->getList($compCriteria)->getItems();

            $availableGroupIds = [];
            foreach ($components as $component) {
                if ($component->getEstoque() > 0) {
                    $availableGroupIds[(int) $component->getGrupoId()] = true;
                }
            }

            foreach ($groups as $group) {
                $groupId = (int) $group->getGrupoId();
                if (!isset($availableGroupIds[$groupId])) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            $this->_logger->error('Error checking atelie groups availability: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build readable label/value list for display.
     *
     * @param array $configData
     * @return array<int, array{label: string, value: string}>
     */
    private function buildReadableOptions(array $configData): array
    {
        $readable = [];

        if (isset($configData['tamanho'])) {
            $readable[] = [
                'label' => (string) __('Tamanho'),
                'value' => (string) $configData['tamanho'],
            ];
        }

        if (!empty($configData['gravacao'])) {
            $readable[] = [
                'label' => (string) __('Gravação'),
                'value' => (string) $configData['gravacao'],
            ];
        }

        $selections = $configData['selecoes'] ?? $configData['selections'] ?? [];
        if (is_array($selections)) {
            foreach ($selections as $selection) {
                $groupCode = (string) ($selection['grupo'] ?? $selection['group'] ?? '');
                $compCode = (string) ($selection['componente'] ?? $selection['component'] ?? '');
                if ($groupCode === '') {
                    continue;
                }

                $readable[] = [
                    'label' => $this->getGroupLabel($groupCode),
                    'value' => $this->getComponentLabel($compCode),
                ];
            }
        }

        return $readable;
    }

    /**
     * Get readable label for a group code.
     *
     * @param string $groupCode
     * @return string
     */
    private function getGroupLabel(string $groupCode): string
    {
        if (isset($this->groupLabels[$groupCode])) {
            return $this->groupLabels[$groupCode];
        }

        try {
            $group = $this->groupRepository->getByCodigo($groupCode);
            if ($group->getNome()) {
                $this->groupLabels[$groupCode] = (string) $group->getNome();
                return $this->groupLabels[$groupCode];
            }
        } catch (\Exception $exception) {
            $this->_logger->debug('Error loading atelie group label: ' . $exception->getMessage());
        }

        $label = ucfirst(str_replace('_', ' ', $groupCode));
        $this->groupLabels[$groupCode] = $label;

        return $label;
    }

    /**
     * Get readable label for a component code.
     *
     * @param string $compCode
     * @return string
     */
    private function getComponentLabel(string $compCode): string
    {
        if (isset($this->componentLabels[$compCode])) {
            return $this->componentLabels[$compCode];
        }

        try {
            $component = $this->componentRepository->getByCodigo($compCode);
            if ($component->getNome()) {
                $this->componentLabels[$compCode] = (string) $component->getNome();
                return $this->componentLabels[$compCode];
            }
        } catch (\Exception $exception) {
            $this->_logger->debug('Error loading atelie component label: ' . $exception->getMessage());
        }

        $this->componentLabels[$compCode] = $compCode;

        return $compCode;
    }
}
