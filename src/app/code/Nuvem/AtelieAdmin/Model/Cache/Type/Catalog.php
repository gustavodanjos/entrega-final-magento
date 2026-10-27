<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

class Catalog extends TagScope
{
    public const TYPE_IDENTIFIER = 'nuvem_atelie_catalog';
    public const CACHE_TAG = 'NUVEM_ATELIE_CATALOG';
    public const IDENTITY_TAG = 'atelie_catalogo';

    public function __construct(FrontendPool $cacheFrontendPool)
    {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}
