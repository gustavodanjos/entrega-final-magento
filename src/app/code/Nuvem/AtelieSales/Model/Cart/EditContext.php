<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\Cart;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class EditContext
{
    public function __construct(
        private readonly Session $session,
        private readonly RequestInterface $request,
        private readonly ConfigurationSerializer $serializer,
        private readonly Json $json
    ) {
    }

    public function isEditing(): bool
    {
        return $this->request->getFullActionName() === 'checkout_cart_configure';
    }

    public function getItemOptions(): array
    {
        if (!$this->isEditing()) {
            return [];
        }
        $item = $this->session->getQuote()->getItemById((int) $this->request->getParam('id'));
        if (!$item || $item->getProductType() !== Atelie::TYPE_CODE
            || (int) $item->getProductId() !== (int) $this->request->getParam('product_id')
        ) {
            throw new LocalizedException(__('O item do carrinho não foi encontrado.'));
        }
        $product = $item->getProduct();
        $options = $product->getTypeInstance()->getOrderOptions($product);
        $data = $options[Atelie::CUSTOM_OPTION_CONFIGURACAO] ?? null;
        $serialized = is_array($data) ? $this->json->serialize($data) : '';
        if (!$item->getOptionByCode(Atelie::CUSTOM_OPTION_CONFIGURACAO)) {
            $serialized = '';
        }
        try {
            $configuration = $this->serializer->unserialize((string) $serialized);
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(__('A configuração do item não pôde ser lida.'), $exception);
        }
        return [
            'item_id' => (int) $item->getId(),
            'qty' => (float) $item->getQty(),
            'configuration' => $this->json->unserialize($this->serializer->serialize($configuration)),
        ];
    }
}
