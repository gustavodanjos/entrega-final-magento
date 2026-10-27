<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Catalog;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Model\AtelieCatalog;
use Nuvem\Atelie\Model\Component;
use Nuvem\Atelie\Model\Config;
use Nuvem\Atelie\Model\Rule;
use Nuvem\AtelieAdmin\Model\Cache\Type\Catalog;

class CachedCatalog implements AtelieCatalogInterface
{
    private const LIFETIME = 3600;

    public function __construct(
        private readonly AtelieCatalog $catalog,
        private readonly Catalog $cache,
        private readonly StateInterface $cacheState,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $serializer
    ) {
    }

    public function getBySku(string $sku): array
    {
        if (!$this->cacheState->isEnabled(Catalog::TYPE_IDENTIFIER)) {
            return $this->catalog->getBySku($sku);
        }

        $storeId = (int) $this->storeManager->getStore()->getId();
        $keyData = [
            'version' => 1,
            'sku' => $sku,
            'store_id' => $storeId,
            'engraving_price' => $this->scopeConfig->getValue(
                Config::XML_PATH_ENGRAVING_PRICE,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'engraving_limit' => $this->scopeConfig->getValue(
                Config::XML_PATH_ENGRAVING_LIMIT,
                ScopeInterface::SCOPE_STORE,
                $storeId
            )
        ];
        $identifier = Catalog::TYPE_IDENTIFIER . '_' . hash('sha256', $this->serializer->serialize($keyData));
        $cached = $this->cache->load($identifier);
        if ($cached !== false) {
            try {
                $data = $this->serializer->unserialize($cached);
                if (is_array($data) && ($data['sku'] ?? null) === $sku
                    && isset($data['grupos'], $data['regras'])
                ) {
                    return $data;
                }
            } catch (\InvalidArgumentException) {
                $this->cache->remove($identifier);
            }
        }

        $data = $this->catalog->getBySku($sku);
        $tags = [
            Catalog::IDENTITY_TAG,
            'atelie_catalogo_sku_' . hash('sha256', $sku),
            Product::CACHE_TAG,
            Component::CACHE_TAG,
            Rule::CACHE_TAG
        ];
        foreach ($data['grupos'] ?? [] as $group) {
            foreach ($group['componentes'] ?? [] as $component) {
                if (!empty($component['codigo'])) {
                    $tags[] = Component::STOREFRONT_CACHE_TAG_PREFIX . $component['codigo'];
                }
            }
        }
        $this->cache->save(
            $this->serializer->serialize($data),
            $identifier,
            array_values(array_unique($tags)),
            self::LIFETIME
        );
        return $data;
    }
}
