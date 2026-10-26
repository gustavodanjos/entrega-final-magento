<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Api;

use Nuvem\AtelieSales\Api\Data\AvailabilityInterface;

/**
 * Public component availability service.
 *
 * @api
 */
interface AvailabilityManagementInterface
{
    /**
     * Get availability flags without exposing stock quantities.
     *
     * @param string $sku
     * @return AvailabilityInterface[]
     */
    public function getAvailability(string $sku): array;
}
