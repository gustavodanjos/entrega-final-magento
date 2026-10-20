<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Plugin\CatalogInventory\StockState;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Nuvem\Atelie\Model\Product\Type\Atelie;

/**
 * Bypasses MSI quote item qty validation for Atelie products where stock is managed by component.
 */
class CheckAtelieQuoteItemQtyPlugin
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param DataObjectFactory $dataObjectFactory
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly DataObjectFactory $dataObjectFactory
    ) {
    }

    /**
     * Skip MSI source item check for Atelie product type.
     *
     * @param StockStateInterface $subject
     * @param \Closure $proceed
     * @param int|string $productId
     * @param float $itemQty
     * @param float $qtyToCheck
     * @param float $origQty
     * @param int|null $scopeId
     * @return DataObject
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundCheckQuoteItemQty(
        StockStateInterface $subject,
        \Closure $proceed,
        $productId,
        $itemQty,
        $qtyToCheck,
        $origQty,
        $scopeId = null
    ): DataObject {
        $product = $this->productRepository->getById((int) $productId);
        if ($product->getTypeId() === Atelie::TYPE_CODE) {
            $result = $this->dataObjectFactory->create();
            $result->setHasError(false);
            return $result;
        }

        return $proceed($productId, $itemQty, $qtyToCheck, $origQty, $scopeId);
    }
}
