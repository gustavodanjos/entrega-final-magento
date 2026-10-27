<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Model\Resolver\Configurador;

use Magento\Catalog\Model\Product;
use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\Rule;

class Identity implements IdentityInterface
{
    public function getIdentities(array $resolvedData): array
    {
        if (empty($resolvedData['sku'])) {
            return [];
        }

        $tags = [
            Product::CACHE_TAG,
            Component::CACHE_TAG,
            Rule::CACHE_TAG,
        ];

        foreach ($resolvedData['grupos'] ?? [] as $grupo) {
            foreach ($grupo['componentes'] ?? [] as $componente) {
                if (!empty($componente['codigo'])) {
                    $tags[] = Component::STOREFRONT_CACHE_TAG_PREFIX
                        . $componente['codigo'];
                }
            }
        }

        return array_values(array_unique($tags));
    }
}
