<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\CustomerData;

use Magento\Catalog\Helper\Image;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Catalog\Model\Product\Configuration\Item\ItemResolverInterface;
use Magento\Checkout\CustomerData\DefaultItem;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Msrp\Helper\Data as MsrpHelper;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use Nuvem\AtelieStudio\Api\PreviewInterface;

/**
 * Minicart item of the atelie type. The core template renders option values
 * with a text binding, so the rows stay raw; only the image becomes the
 * client's creation.
 */
class AtelieItem extends DefaultItem
{
    /**
     * @var OptionListBuilder
     */
    private OptionListBuilder $optionListBuilder;

    /**
     * @var PreviewInterface
     */
    private PreviewInterface $preview;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @param Image $imageHelper
     * @param MsrpHelper $msrpHelper
     * @param UrlInterface $urlBuilder
     * @param ConfigurationPool $configurationPool
     * @param CheckoutHelper $checkoutHelper
     * @param OptionListBuilder $optionListBuilder
     * @param PreviewInterface $preview
     * @param Escaper|null $escaper
     * @param ItemResolverInterface|null $itemResolver
     */
    public function __construct(
        Image $imageHelper,
        MsrpHelper $msrpHelper,
        UrlInterface $urlBuilder,
        ConfigurationPool $configurationPool,
        CheckoutHelper $checkoutHelper,
        OptionListBuilder $optionListBuilder,
        PreviewInterface $preview,
        ?Escaper $escaper = null,
        ?ItemResolverInterface $itemResolver = null
    ) {
        parent::__construct(
            $imageHelper,
            $msrpHelper,
            $urlBuilder,
            $configurationPool,
            $checkoutHelper,
            $escaper,
            $itemResolver
        );
        $this->optionListBuilder = $optionListBuilder;
        $this->preview = $preview;
    }

    /**
     * Raw rows: the minicart template escapes values on output.
     *
     * @return array<int, array{label: string, value: string}>
     */
    protected function getOptionList(): array
    {
        return $this->optionListBuilder->build($this->item);
    }

    /**
     * Extend the default data with the creation image.
     *
     * @return array
     */
    protected function doGetItemData(): array
    {
        $data = parent::doGetItemData();

        $serialized = $this->optionListBuilder->getSerializedConfiguration($this->item);
        if ($serialized !== null && isset($data['product_image']['src'])) {
            $data['product_image']['src'] = $this->preview->getUrl($serialized);
        }

        return $data;
    }
}
