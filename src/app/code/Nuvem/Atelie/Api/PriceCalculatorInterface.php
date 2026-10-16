<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api;

use Nuvem\Atelie\Api\Data\ConfigurationInterface;

/**
 * Calculates the sneaker price on the server, always from catalog data.
 *
 * The price shown in the browser is a mirror only: the server never trusts it.
 *
 * @api
 */
interface PriceCalculatorInterface
{
    /**
     * Calculate the final price of a configuration.
     *
     * On error the price is never guessed: the calculation fails loudly so a
     * broken call cannot put a wrong price on the cart.
     *
     * @param string $sku
     * @param ConfigurationInterface $configuration
     * @param int|null $storeId store scope for price and configuration, null means the current store
     * @return float price in BRL, without taxes
     * @throws \Magento\Framework\Exception\NoSuchEntityException when the SKU does not exist or is not an atelie product
     */
    public function calculate(string $sku, ConfigurationInterface $configuration, ?int $storeId = null): float;
}
