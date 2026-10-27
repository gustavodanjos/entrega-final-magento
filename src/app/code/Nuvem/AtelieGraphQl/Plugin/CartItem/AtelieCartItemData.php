<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Plugin\CartItem;

use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\QuoteGraphQl\Model\CartItem\GetItemsData;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\AtelieStudio\Api\PreviewInterface;

class AtelieCartItemData
{
    public function __construct(
        private readonly ConfigurationSerializer $serializer,
        private readonly PreviewInterface $preview
    ) {
    }

    public function afterExecute(
        GetItemsData $subject,
        array $result,
        array $cartItems
    ): array {
        foreach ($result as $index => $itemData) {
            if (!is_array($itemData)) {
                continue;
            }

            $cartItem = $itemData['model'] ?? null;

            if (!$cartItem instanceof QuoteItem) {
                continue;
            }

            if ($cartItem->getProductType() !== Atelie::TYPE_CODE) {
                continue;
            }

            $option = $cartItem->getOptionByCode(
                Atelie::CUSTOM_OPTION_CONFIGURACAO
            );

            if (!$option || !is_string($option->getValue())
                || $option->getValue() === ''
            ) {
                throw new LocalizedException(
                    __('Atelie cart item configuration was not found.')
                );
            }

            $serialized = $option->getValue();

            try {
                $configuration = $this->serializer->unserialize(
                    $serialized
                );
            } catch (\InvalidArgumentException $e) {
                throw new LocalizedException(
                    __('Invalid Atelie cart item configuration.'),
                    $e
                );
            }

            $selections = [];

            foreach ($configuration->getSelections() as $selection) {
                $selections[] = [
                    'grupo' => $selection->getGrupo(),
                    'componente' => $selection->getComponente()
                ];
            }

            $result[$index]['atelie_configuracao'] = [
                'tamanho' => $configuration->getTamanho(),
                'gravacao' => $configuration->getGravacao(),
                'selecoes' => $selections
            ];

            $result[$index]['preview_url'] = $this->preview->getUrl(
                $serialized
            );
        }

        return $result;
    }
}
