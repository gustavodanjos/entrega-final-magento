<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Pricing\Render;

use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\Catalog\Pricing\Price\RegularPrice;
use Magento\Catalog\Pricing\Render\FinalPriceBox as CatalogFinalPriceBox;
use Magento\Framework\Pricing\Render as PricingRender;

/**
 * Price box renderer for Ateliê products.
 *
 * In category listing and catalog search, renders the base indexed price
 * preceded by the "a partir de" label without loading component or studio entities.
 * On product view (PDP), the "a partir de" label is omitted so the studio
 * total mirror price is highlighted.
 */
class FinalPriceBox extends CatalogFinalPriceBox
{
    /**
     * Check if price rendering should be done in product listing mode.
     *
     * @return bool
     */
    public function isProductList(): bool
    {
        $isProductList = $this->getData('is_product_list');
        if ($isProductList !== null) {
            return (bool)$isProductList;
        }

        return $this->getZone() === PricingRender::ZONE_ITEM_LIST;
    }

    /**
     * Determine whether the "a partir de" starting price label should be displayed.
     *
     * In category listing and catalog search, atelie products show "a partir de".
     * On the product page (item_view), the label disappears and the studio mirror price takes precedence.
     *
     * @return bool
     */
    public function showStartingAt(): bool
    {
        return $this->isProductList() || $this->getZone() !== PricingRender::ZONE_ITEM_VIEW;
    }

    /**
     * Get label for starting price.
     *
     * @return \Magento\Framework\Phrase
     */
    public function getStartingAtLabel(): \Magento\Framework\Phrase
    {
        return __('a partir de');
    }

    /**
     * Define if the special price should be shown.
     *
     * @return bool
     */
    public function hasSpecialPrice(): bool
    {
        if ($this->isProductList()) {
            $specialPriceMap = $this->getData('special_price_map');
            if (is_array($specialPriceMap) && isset($specialPriceMap[$this->saleableItem->getId()])) {
                return (bool)$specialPriceMap[$this->saleableItem->getId()];
            }
        }

        $regularPrice = $this->getPriceType(RegularPrice::PRICE_CODE);
        $finalPrice = $this->getPriceType(FinalPrice::PRICE_CODE);

        if ($regularPrice && $finalPrice) {
            return $finalPrice->getAmount()->getValue() < $regularPrice->getAmount()->getValue();
        }

        return false;
    }
}
