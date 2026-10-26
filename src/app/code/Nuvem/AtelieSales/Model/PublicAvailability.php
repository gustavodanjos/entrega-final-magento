<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\InputException;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieSales\Api\ComponentStockInterface;
use Nuvem\AtelieSales\Api\PublicAvailabilityInterface;

class PublicAvailability implements PublicAvailabilityInterface
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param ComponentStockInterface $componentStock
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ComponentStockInterface $componentStock
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getBySku(string $sku): array
    {
        $product = $this->productRepository->get($sku);
        if ($product->getTypeId() !== Atelie::TYPE_CODE) {
            throw new InputException(__('O produto %1 não é do tipo ateliê.', $sku));
        }

        $availability = [];
        foreach ($this->componentStock->availability($sku) as $component) {
            $availability[] = [
                'componente' => $component['component'],
                'disponivel' => $component['available'],
                'ultimas_unidades' => $component['last_units'],
            ];
        }

        return $availability;
    }
}
