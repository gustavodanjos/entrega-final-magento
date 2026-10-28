<?php
declare(strict_types=1);

namespace Nuvem\AtelieStudio\Block;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nuvem\Atelie\Api\AtelieCatalogInterface;
use Nuvem\Atelie\Model\Component;
use Psr\Log\LoggerInterface;

class Studio extends Template implements IdentityInterface
{
    public const CATALOG_CACHE_TAG = 'atelie_catalogo';

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly AtelieCatalogInterface $atelieCatalog,
        private readonly LoggerInterface $logger,
        array $data = []
    ) {
        parent::__construct($context,$data);
    }

    public function getProduct(): ?Product
    {
        $product =$this->registry->registry('current_product');
        return $product instanceof Product ? $product : null;
    }

    public function getIdentities(): array
    {
        $identities = [];
        $product =$this->getProduct();

        if ($product) {$identities[] = Product::CACHE_TAG . '_' . $product->getId();$identities[] = self::CATALOG_CACHE_TAG;

            try {
                $catalogData = $this->atelieCatalog->getBySku($product->getSku());
                if (!empty($catalogData['grupos'])) {
                    foreach ($catalogData['grupos'] as$group) {
                        foreach ($group['componentes'] ?? [] as $component) {$sku = $component['codigo'] ?? $component['sku'] ?? null;
                            if (!empty($sku)) {
                                $identities[] = Component::STOREFRONT_CACHE_TAG_PREFIX .$sku;
                            }
                        }
                    }
                }
            } catch (\Exception $e) {$this->logger->error(
                    sprintf('Error resolving Atelie identities for product %s: %s', $product->getSku(),$e->getMessage()),
                    ['exception' => $e]
                );
            }
        }

        return array_values(array_unique($identities));
    }
}
