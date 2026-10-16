<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\PriceCalculatorInterface;

/**
 * Provisional price: the base price of the SKU, straight from kit/dados/produtos.csv.
 *
 * Component additions and engraving only enter in the real calculator (F2.1),
 * so every front shares the same starting price until then.
 */
class ProvisionalPriceCalculator implements PriceCalculatorInterface
{
    private const BASE_PRICES = [
        'NUVEM-RUNNER' => 449.90,
        'NUVEM-MINI' => 329.90,
    ];

    public function calculate(
        string $sku,
        ConfigurationInterface $configuration,
        ?int $storeId = null
    ): float {
        if (!isset(self::BASE_PRICES[$sku])) {
            throw new NoSuchEntityException(
                __('Product with SKU "%1" does not exist.', $sku)
            );
        }

        return self::BASE_PRICES[$sku];
    }
}
