<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Plugin\Component;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\Data\ComponentInterface;
use Nuvem\Atelie\Model\Component;

class InvalidateCache
{
    private const CATALOG_TAG = 'atelie_catalogo';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ManagerInterface $eventManager
    ) {
    }

    public function afterSave(
        ComponentRepositoryInterface $subject,
        ComponentInterface $result
    ): ComponentInterface {
        $tags = [
            self::CATALOG_TAG,
            Component::CACHE_TAG,
        ];

        if ($result->getComponenteId() !== null) {
            $tags[] = Component::CACHE_TAG
                . '_'
                . $result->getComponenteId();
        }

        if ($result->getCodigo()) {
            $tags[] = 'atelie_comp_' . $result->getCodigo();
        }

        $tags = array_values(array_unique($tags));

        $this->cache->clean($tags);

        $identities = new class($tags) implements IdentityInterface {
            public function __construct(
                private readonly array $tags
            ) {
            }

            public function getIdentities(): array
            {
                return $this->tags;
            }
        };

        $this->eventManager->dispatch(
            'clean_cache_by_tags',
            ['object' => $identities]
        );

        return $result;
    }
}