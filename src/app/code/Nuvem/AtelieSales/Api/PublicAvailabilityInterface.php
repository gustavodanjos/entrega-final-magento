<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Api;

/**
 * Public availability without stock quantities.
 *
 * @api
 */
interface PublicAvailabilityInterface
{
    /**
     * Return component codes and availability flags in the public REST format.
     *
     * @param string $sku
     * @return mixed[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException When the SKU does not exist.
     * @throws \Magento\Framework\Exception\InputException When the product is not an atelie product.
     */
    public function getBySku(string $sku): array;
}
