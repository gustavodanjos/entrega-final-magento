<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Nuvem\AtelieSales\Api\AvailabilityManagementInterface;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Api\Data\AvailabilityInterface;

/**
 * Maps internal stock flags to the public availability contract.
 */
class AvailabilityManagement implements AvailabilityManagementInterface
{
    /**
     * @param ComponentStockInterface $componentStock
     */
    public function __construct(private readonly ComponentStockInterface $componentStock)
    {
    }

    /**
     * @inheritDoc
     */
    public function getAvailability(string $sku): array
    {
        return array_map(
            static fn (array $availability): AvailabilityInterface => new Availability([
                'componente' => $availability['component'],
                'disponivel' => $availability['available'],
                'ultimas_unidades' => $availability['last_units'],
            ]),
            $this->componentStock->availability($sku)
        );
    }
}
