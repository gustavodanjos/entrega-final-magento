<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Catalog\Product\Type;

use Magento\Catalog\Model\Product\Type;
use Nuvem\AtelieSales\Model\Product\Price;

class PriceModelPlugin
{
    public function __construct(
        private Price $priceModel
    ) {
    }

    public function aroundPriceFactory(Type $subject, callable $proceed, string $productType)
    {
        if ($productType === 'atelie') {
            return $this->priceModel;
        }

        return $proceed($productType);
    }
}
