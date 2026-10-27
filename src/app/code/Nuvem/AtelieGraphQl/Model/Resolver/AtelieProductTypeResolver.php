<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Model\Resolver;

use Magento\Framework\GraphQl\Query\Resolver\TypeResolverInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class AtelieProductTypeResolver implements TypeResolverInterface
{
    public function resolveType(array $data): string
    {
        return ($data['type_id'] ?? null) === Atelie::TYPE_CODE
            ? 'AtelieProduct'
            : '';
    }
}
