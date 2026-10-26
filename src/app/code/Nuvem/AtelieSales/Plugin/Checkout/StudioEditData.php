<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Plugin\Checkout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Nuvem\AtelieSales\Model\Cart\EditContext;
use Nuvem\AtelieStudio\ViewModel\Estudio;

class StudioEditData
{
    public function __construct(
        private readonly EditContext $context,
        private readonly UrlInterface $url
    ) {
    }

    public function afterGetStudioData(Estudio $subject, array $result): array
    {
        if (!$this->context->isEditing() || !$result) {
            return $result;
        }
        $result['editing'] = true;
        try {
            $options = $this->context->getItemOptions();
            $result['item_id'] = $options['item_id'];
            $result['qty'] = $options['qty'];
            $result['configuracao_inicial'] = $options['configuration'];
            $result['urls']['update_item'] = $this->url->getUrl(
                'checkout/cart/updateItemOptions',
                ['id' => $options['item_id']]
            );
        } catch (LocalizedException $exception) {
            $result['edit_error'] = $exception->getMessage();
        }
        return $result;
    }
}
