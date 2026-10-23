<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Block\Checkout\Cart\Item\Renderer;

use Magento\Catalog\Block\Product\ImageBuilder;
use Magento\Catalog\Helper\Product\Configuration as ProductConfiguration;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Checkout\Block\Cart\Item\Renderer as CartItemRenderer;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Url\Helper\Data as UrlHelper;
use Magento\Framework\View\Element\Message\InterpretationStrategyInterface;
use Magento\Framework\View\Element\Template\Context;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use Nuvem\AtelieStudio\Api\PreviewInterface;

/**
 * Cart page renderer of the atelie type: the option list is the sneaker
 * configuration and the item image is the client's creation.
 */
class Atelie extends CartItemRenderer
{
    private OptionListBuilder $optionListBuilder;

    private PreviewInterface $preview;

    public function __construct(
        Context $context,
        ProductConfiguration $productConfig,
        CheckoutSession $checkoutSession,
        ImageBuilder $imageBuilder,
        UrlHelper $urlHelper,
        ManagerInterface $messageManager,
        PriceCurrencyInterface $priceCurrency,
        Manager $moduleManager,
        InterpretationStrategyInterface $messageInterpretationStrategy,
        OptionListBuilder $optionListBuilder,
        PreviewInterface $preview,
        array $data = [],
        ?ItemResolverInterface $itemResolver = null
    ) {
        parent::__construct(
            $context,
            $productConfig,
            $checkoutSession,
            $imageBuilder,
            $urlHelper,
            $messageManager,
            $priceCurrency,
            $moduleManager,
            $messageInterpretationStrategy,
            $data,
            $itemResolver
        );
        $this->optionListBuilder = $optionListBuilder;
        $this->preview = $preview;
    }

    /**
     * Raw rows: the cart template escapes values on output.
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getOptionList(): array
    {
        return $this->optionListBuilder->build($this->getItem());
    }

    /**
     * URL of the client's creation, or an empty string when the item carries
     * no configuration and the template falls back to the product image.
     */
    public function getPreviewImageUrl(): string
    {
        $serialized = $this->optionListBuilder->getSerializedConfiguration($this->getItem());

        return $serialized === null ? '' : $this->preview->getUrl($serialized);
    }
}
