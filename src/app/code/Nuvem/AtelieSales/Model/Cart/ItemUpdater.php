<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\Cart;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Processor;

class ItemUpdater
{
    public function __construct(
        private readonly ProductRepositoryInterface $products,
        private readonly Processor $processor,
        private readonly ManagerInterface $events
    ) {
    }

    public function update(Quote $quote, Item $item, DataObject $request): Item
    {
        $product = clone $this->products->getById($item->getProductId(), false, $quote->getStoreId(), true);
        if (!$product->isSalable()) {
            throw new LocalizedException(__('Este produto não está disponível.'));
        }
        $request = new DataObject([
            'atelie' => $product->processBuyRequest($request)->getData('atelie'),
            'qty' => $item->getQty(),
            'id' => $item->getId(),
            'reset_count' => true,
        ]);
        $candidates = $product->getTypeInstance()->prepareForCartAdvanced(
            $request,
            $product,
            AbstractType::PROCESS_MODE_FULL
        );
        if (!is_array($candidates) || count($candidates) !== 1) {
            throw new LocalizedException(__(is_string($candidates) ? $candidates : 'Configuração inválida.'));
        }
        $candidate = reset($candidates);
        $candidate->unsetData('final_price');

        // Validate quantity and price on a detached item before touching the persisted line.
        $prepared = $this->processor->init($candidate, $request);
        $prepared->setQuote($quote);
        $prepared->setOptions($candidate->getCustomOptions());
        $prepared->setProduct($candidate);
        $this->processor->prepare($prepared, $request, $candidate);
        if ($prepared->getHasError()) {
            throw new LocalizedException(__((string) $prepared->getMessage()));
        }

        $item->setOptions($candidate->getCustomOptions());
        $item->setProduct($candidate);
        $item->setCustomPrice(null)->setOriginalCustomPrice(null);
        foreach (['calculation_price', 'base_calculation_price', 'converted_price', 'original_price',
            'base_original_price', 'calculation_price_original', 'base_calculation_price_original'] as $key) {
            $item->unsetData($key);
        }
        $item->setPrice($prepared->getPrice());
        $quote->setTotalsCollectedFlag(false);
        $this->events->dispatch('sales_quote_product_add_after', ['items' => [$item]]);
        return $item;
    }
}
