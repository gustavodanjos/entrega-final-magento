<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Checkout;

use Magento\Checkout\CustomerData\Cart;
use Magento\Checkout\Model\Session;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use Nuvem\AtelieStudio\Api\PreviewInterface;

class CartSection
{
    public function __construct(
        private readonly Session $session,
        private readonly OptionListBuilder $options,
        private readonly PreviewInterface $preview
    ) {
    }

    public function afterGetSectionData(Cart $subject, array $result): array
    {
        $result['atelie_items'] = [];
        foreach ($this->session->getQuote()->getAllVisibleItems() as $item) {
            if ($item->getProductType() !== Atelie::TYPE_CODE) {
                continue;
            }
            $configuration = $this->options->getSerializedConfiguration($item);
            if ($configuration !== null) {
                $result['atelie_items'][(int) $item->getId()] = [
                    'image' => $this->preview->getUrl($configuration),
                ];
            }
        }
        return $result;
    }
}
