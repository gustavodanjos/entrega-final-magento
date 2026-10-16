<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Model\Resolver\Galeria;

use Magento\Framework\GraphQl\Query\Resolver\IdentityInterface;

class Identity implements IdentityInterface
{
    public function getIdentities(array $resolvedData): array
    {
        return [];
    }
}
