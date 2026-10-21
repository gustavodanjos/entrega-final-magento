<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\Product;

use Magento\Catalog\Api\Data\ProductTierPriceExtensionFactory;
use Magento\Catalog\Api\Data\ProductTierPriceInterfaceFactory;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\Price as ProductTypePrice;
use Magento\CatalogRule\Model\ResourceModel\RuleFactory;
use Magento\Customer\Api\GroupManagementInterface;
use Magento\Customer\Model\Session;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\PriceCalculatorInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;


class Price extends ProductTypePrice
{
    private PriceCalculatorInterface $priceCalculator;
    private AtelieCatalogInterface $catalog;
    private ConfigurationSerializer $configurationSerializer;

    /**
     * Same per-request reuse as PriceCalculator::catalogMemo: totals call
     * getFinalPrice multiple times per item and each getBySku loads the
     * product plus three catalog collections.
     */
    private array $catalogBaseMemo = [];

    public function __construct(
        PriceCalculatorInterface $priceCalculator,
        AtelieCatalogInterface $catalog,
        ConfigurationSerializer $configurationSerializer,
        RuleFactory $ruleFactory,
        StoreManagerInterface $storeManager,
        TimezoneInterface $localeDate,
        Session $customerSession,
        ManagerInterface $eventManager,
        PriceCurrencyInterface $priceCurrency,
        GroupManagementInterface $groupManagement,
        ProductTierPriceInterfaceFactory $tierPriceFactory,
        ScopeConfigInterface $config,
        ?ProductTierPriceExtensionFactory $tierPriceExtensionFactory = null
    ) {
        parent::__construct(
            $ruleFactory,
            $storeManager,
            $localeDate,
            $customerSession,
            $eventManager,
            $priceCurrency,
            $groupManagement,
            $tierPriceFactory,
            $config,
            $tierPriceExtensionFactory
        );
        $this->priceCurrency = $priceCurrency;
        $this->priceCalculator = $priceCalculator;
        $this->catalog = $catalog;
        $this->configurationSerializer = $configurationSerializer;
    }

    /**
     * @param float|null $qty
     * @param Product $product
     * @return float
     */
    public function getFinalPrice($qty, $product)
    {
        $base = $this->resolveProductBasePrice($qty, $product);
        $configuration = $this->readConfiguration($product);

        if ($configuration === null) {
            return $base;
        }

        $sku = (string)$product->getSku();
        $fullPrice = $this->priceCalculator->calculate(
            $sku,
            $configuration,
            (int)$product->getStoreId()
        );
        $catalogBase = $this->catalogBaseMemo[$sku]
            ??= (float)$this->catalog->getBySku($sku)['preco_base'];

        $finalPrice = $this->priceCurrency->round($base + ($fullPrice - $catalogBase));
        $product->setFinalPrice($finalPrice);

        return $finalPrice;
    }

    protected function resolveProductBasePrice($qty, $product)
    {
        return parent::getFinalPrice($qty, $product);
    }

    private function readConfiguration(Product $product): ?ConfigurationInterface
    {
        $option = $product->getCustomOption('atelie_configuracao');
        if ($option === null || trim((string)$option->getValue()) === '') {
            return null;
        }

        return $this->configurationSerializer->unserialize((string)$option->getValue());
    }
}
