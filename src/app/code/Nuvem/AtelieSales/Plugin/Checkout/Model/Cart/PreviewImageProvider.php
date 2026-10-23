<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Checkout\Model\Cart;

use Magento\Checkout\Model\Cart\ImageProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;
use Nuvem\AtelieStudio\Api\PreviewInterface;

/**
 * Replaces the checkout summary thumbnail of atelie items with the image of
 * the client's creation (PreviewInterface of the studio module).
 */
class PreviewImageProvider
{
    /**
     * @param CheckoutSession $checkoutSession
     * @param OptionListBuilder $optionListBuilder
     * @param PreviewInterface $preview
     */
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly OptionListBuilder $optionListBuilder,
        private readonly PreviewInterface $preview
    ) {
    }

    /**
     * Swap the thumbnail of atelie items with the image of the client's
     * creation.
     *
     * @param ImageProvider $subject
     * @param array<int, array{src: string, alt: string, width: int|null, height: int|null}> $result
     * @param int|string $cartId
     * @return array
     */
    public function afterGetImages(ImageProvider $subject, array $result, $cartId): array
    {
        $quote = $this->checkoutSession->getQuote();
        if ((int) $quote->getId() !== (int) $cartId) {
            return $result;
        }

        foreach ($quote->getAllItems() as $item) {
            $serialized = $this->optionListBuilder->getSerializedConfiguration($item);
            if ($serialized === null) {
                continue;
            }
            $itemId = $item->getItemId();
            if (isset($result[$itemId])) {
                $result[$itemId]['src'] = $this->preview->getUrl($serialized);
            }
        }

        return $result;
    }
}
