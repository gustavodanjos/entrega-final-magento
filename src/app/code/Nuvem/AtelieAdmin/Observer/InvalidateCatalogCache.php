<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Observer;

use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Nuvem\AtelieAdmin\Model\Cache\Type\Catalog;

class InvalidateCatalogCache implements ObserverInterface
{
    public function __construct(
        private readonly Catalog $cache,
        private readonly ManagerInterface $eventManager
    ) {
    }

    public function execute(Observer $observer): void
    {
        $this->cache->clean();
        $identities = new class implements IdentityInterface {
            public function getIdentities(): array
            {
                return [Catalog::IDENTITY_TAG];
            }
        };
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $identities]);
    }
}
