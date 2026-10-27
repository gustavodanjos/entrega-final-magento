<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Plugin\Rule;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface;
use Nuvem\Atelie\Api\Data\RuleInterface;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\Rule;

class InvalidateCache
{
    private const CATALOG_TAG = 'atelie_catalogo';

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ManagerInterface $eventManager
    ) {
    }

    public function afterSave(
        RuleRepositoryInterface $subject,
        RuleInterface $result
    ): RuleInterface {
        $tags = [
            self::CATALOG_TAG,
            Rule::CACHE_TAG,
        ];

        if ($result->getRegraId() !== null) {
            $tags[] = Rule::CACHE_TAG . '_' . $result->getRegraId();
        }

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