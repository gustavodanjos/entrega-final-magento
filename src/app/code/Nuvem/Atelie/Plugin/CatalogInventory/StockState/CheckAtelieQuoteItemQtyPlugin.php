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
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Model\Product\Type\Atelie;

/**
 * Bypasses MSI quote item qty validation exclusively for Atelie customizable products.
 *
 * CONTEXT & RATIONALE:
 * In Magento MSI (Magento_InventorySales), CheckQuoteItemQtyPlugin unconditionally intercepts
 * StockStateInterface::checkQuoteItemQty and delegates to GetBackorder::execute(), which in turn
 * executes IsProductSalableForRequestedQtyConditionChain::validateProductType().
 *
 * That condition chain queries IsSourceItemManagementAllowedForProductTypeInterface, which returns
 * false for any product type declared with isQty="false" in product_types.xml (such as Atelie,
 * where inventory is managed per component rather than on the parent product SKU).
 *
 * When isSourceItemManagementAllowedForProductType returns false, MSI throws a LocalizedException:
 * "Can't check requested quantity for products without Source Items support.", preventing the
 * product from being added to the quote/cart entirely.
 *
 * By registering this around plugin with sortOrder="-10", it executes before MSI's plugin:
 * 1. For Atelie products:
 *    - Validates component availability via $product->isSalable().
 *    - If salable, returns has_error = false, safely bypassing MSI source item verification.
 *    - If NOT salable (e.g. an active group has no in-stock components), returns has_error = true
 *      with the 'out_stock' error code, preserving inventory integrity.
 * 2. For all other product types (Simple, Configurable, Bundle, Virtual, etc.):
 *    - Calls $proceed(), leaving MSI and core CatalogInventory validations completely unaltered.
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
     * Skip MSI source item check for Atelie product type while preserving salability validation.
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
        try {
            $product = $this->productRepository->getById((int) $productId);
        } catch (NoSuchEntityException) {
            return $proceed($productId, $itemQty, $qtyToCheck, $origQty, $scopeId);
        }

        if ($product->getTypeId() !== Atelie::TYPE_CODE) {
            return $proceed($productId, $itemQty, $qtyToCheck, $origQty, $scopeId);
        }

        $result = $this->dataObjectFactory->create();
        if (!$product->isSalable()) {
            $result->setHasError(true)
                ->setMessage(__('This product is out of stock.'))
                ->setQuoteMessage(__('Some of the products are out of stock.'))
                ->setQuoteMessageIndex('qty')
                ->setErrorCode('out_stock');
            return $result;
        }

        $result->setHasError(false);
        return $result;
    }
}
