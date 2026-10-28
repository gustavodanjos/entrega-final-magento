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
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\File\UploaderFactory;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Magento\MediaStorage\Helper\File\Storage\Database as FileStorageDb;
use Nuvem\Atelie\Model\Product\Type\Atelie\AvailabilityChecker;
use Nuvem\Atelie\Model\Product\Type\Atelie\ConfigurationHandler;
use Nuvem\Atelie\Model\Product\Type\Atelie\OrderOptionsBuilder;
use Psr\Log\LoggerInterface;

/**
 * Atelie customizable product type model.
 */
class Atelie extends AbstractType
{
    public const TYPE_CODE = 'atelie';
    public const CUSTOM_OPTION_CONFIGURACAO = 'atelie_configuracao';

    /**
     * @var bool
     */
    protected $_canConfigure = true;

    /**
     * @var bool
     */
    protected $_isComposite = false;

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
     * @param ConfigurationHandler $configurationHandler
     * @param AvailabilityChecker $availabilityChecker
     * @param OrderOptionsBuilder $orderOptionsBuilder
     * @param JsonSerializer|null $serializer
     * @param UploaderFactory|null $uploaderFactory
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
        private readonly ConfigurationHandler $configurationHandler,
        private readonly AvailabilityChecker $availabilityChecker,
        private readonly OrderOptionsBuilder $orderOptionsBuilder,
        ?JsonSerializer $serializer = null,
        ?UploaderFactory $uploaderFactory = null
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
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function isPossibleBuyFromList($product): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hasOptions($product): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hasRequiredOptions($product): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function canConfigure($product): bool
    {
        return ($product instanceof Product ? $product->isAvailable() : true) && $this->_canConfigure;
    }

    /**
     * @inheritdoc
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

        $salable = $this->availabilityChecker->isAvailable();
        $product->setData('all_items_salable', $salable);
        $product->setData('is_salable', $salable ? 1 : 0);
        return $salable;
    }

    /**
     * @inheritdoc
     */
    protected function _prepareProduct(DataObject $buyRequest, $product, $processMode)
    {
        $res = $this->configurationHandler->prepare(
            $buyRequest,
            $product,
            $this->_isStrictProcessMode($processMode),
            $this->getSpecifyOptionMessage()
        );

        if (is_string($res)) {
            return $res;
        }

        return parent::_prepareProduct($buyRequest, $product, $processMode);
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function processBuyRequest($product, $buyRequest): array
    {
        $atelie = $buyRequest->getData('atelie') ?? $buyRequest->getData(self::CUSTOM_OPTION_CONFIGURACAO);
        return $atelie !== null ? ['atelie' => $atelie] : [];
    }

    /**
     * @inheritdoc
     */
    public function getOrderOptions($product): array
    {
        return $this->orderOptionsBuilder->build($product, parent::getOrderOptions($product));
    }

    /**
     * @inheritdoc
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function deleteTypeSpecificData(Product $product): static
    {
        return $this;
    }
}
