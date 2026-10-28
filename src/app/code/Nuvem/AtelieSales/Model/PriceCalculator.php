<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Framework\Exception\LocalizedException;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\PriceCalculatorInterface;

class PriceCalculator implements PriceCalculatorInterface
{
    /**
     * getBySku loads the product plus three catalog collections; totals call
     * getFinalPrice several times per item, so the catalog entry is reused
     * for the lifetime of the shared service instance (one request).
     */
    private array $catalogMemo = [];

    public function __construct(
        private AtelieCatalogInterface $catalog
    ) {
    }

    public function calculate(
        string $sku,
        ConfigurationInterface $configuration,
        ?int $storeId = null
    ): float {
        $product = $this->catalogMemo[$sku] ??= $this->catalog->getBySku($sku);

        $components = [];
        foreach ($product['grupos'] as $group) {
            foreach ($group['componentes'] as $component) {
                $components[$component['codigo']] = $component;
            }
        }

        $total = (float)$product['preco_base'];

        foreach ($configuration->getSelections() as $selection) {
            $code = $selection->getComponent();
            if (!isset($components[$code])) {
                throw new LocalizedException(
                    __('The component "%1" does not exist in the catalog of "%2".', $code, $sku)
                );
            }
            $total += (float)$components[$code]['preco_adicional'];
        }

        if (trim((string)$configuration->getEngraving()) !== '') {
            if ($product['gravacao'] === null) {
                throw new LocalizedException(
                    __('The product "%1" does not allow engraving.', $sku)
                );
            }
            $total += (float)$product['gravacao']['preco'];
        }

        return $total;
    }
}
