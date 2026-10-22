<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Exception;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Quote\Api\Data\CartItemInterface;
use Magento\Quote\Api\Data\ProductOptionExtensionFactory;
use Magento\Quote\Api\Data\ProductOptionInterface;
use Magento\Quote\Model\Quote\Item\CartItemProcessorInterface;
use Magento\Quote\Model\Quote\ProductOptionFactory;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Model\Product\Type\Atelie\BuyRequestReader;

/**
 * Converts Atelie cart item extension attributes to buy request and vice versa.
 */
class CartItemProcessor implements CartItemProcessorInterface
{
    /**
     * @param DataObjectFactory $objectFactory
     * @param ProductOptionFactory $productOptionFactory
     * @param ProductOptionExtensionFactory $productOptionExtensionFactory
     * @param ConfigurationSerializer $configurationSerializer
     * @param BuyRequestReader $buyRequestReader
     */
    public function __construct(
        private readonly DataObjectFactory $objectFactory,
        private readonly ProductOptionFactory $productOptionFactory,
        private readonly ProductOptionExtensionFactory $productOptionExtensionFactory,
        private readonly ConfigurationSerializer $configurationSerializer,
        private readonly BuyRequestReader $buyRequestReader
    ) {
    }

    /**
     * @inheritdoc
     */
    public function convertToBuyRequest(CartItemInterface $cartItem): ?DataObject
    {
        $productOption = $cartItem->getProductOption();
        if (!$productOption instanceof ProductOptionInterface) {
            return null;
        }

        $extensionAttributes = $productOption->getExtensionAttributes();
        if (!$extensionAttributes || !method_exists($extensionAttributes, 'getAtelieConfiguracao')) {
            return null;
        }

        $configuration = $extensionAttributes->getAtelieConfiguracao();
        if (!$configuration instanceof ConfigurationInterface) {
            return null;
        }

        $selections = [];
        foreach ($configuration->getSelections() as $selection) {
            $group = $selection->getGrupo() ?: $selection->getGroup();
            $component = $selection->getComponente() ?: $selection->getComponent();
            $selections[] = [
                'grupo' => $group,
                'componente' => $component,
            ];
        }

        $requestData = [
            'tamanho' => $configuration->getTamanho() ?: $configuration->getSize(),
            'gravacao' => $configuration->getGravacao() ?? $configuration->getEngraving(),
            'selecoes' => $selections,
        ];

        return $this->objectFactory->create(['atelie' => $requestData]);
    }

    /**
     * @inheritdoc
     */
    public function processOptions(CartItemInterface $cartItem): CartItemInterface
    {
        if ($cartItem->getProductType() !== Atelie::TYPE_CODE) {
            return $cartItem;
        }

        $configuration = $this->resolveConfiguration($cartItem);
        if ($configuration === null) {
            return $cartItem;
        }

        $this->saveConfigurationToCustomOption($cartItem, $configuration);

        $productOption = $cartItem->getProductOption() ?: $this->productOptionFactory->create();
        $extensionAttributes = $productOption->getExtensionAttributes()
            ?: $this->productOptionExtensionFactory->create();

        if (method_exists($extensionAttributes, 'setAtelieConfiguracao')) {
            $extensionAttributes->setAtelieConfiguracao($configuration);
            $productOption->setExtensionAttributes($extensionAttributes);
            $cartItem->setProductOption($productOption);
        }

        return $cartItem;
    }

    /**
     * Save configuration as custom option on cart item and product.
     *
     * Persists configuration as quote item option so that Magento quote item rehydration,
     * quote item repository, and the pricing model can resolve the configuration via
     * getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO).
     *
     * @param CartItemInterface $cartItem
     * @param ConfigurationInterface $configuration
     * @return void
     */
    private function saveConfigurationToCustomOption(
        CartItemInterface $cartItem,
        ConfigurationInterface $configuration
    ): void {
        $serialized = $this->configurationSerializer->serialize($configuration);

        if (method_exists($cartItem, 'addOption')) {
            $productId = null;
            if (method_exists($cartItem, 'getProduct') && $cartItem->getProduct()) {
                $productId = $cartItem->getProduct()->getId();
            }
            $cartItem->addOption([
                'product_id' => $productId,
                'code' => Atelie::CUSTOM_OPTION_CONFIGURACAO,
                'value' => $serialized,
            ]);
        }

        if (method_exists($cartItem, 'getProduct')) {
            $product = $cartItem->getProduct();
            if ($product && method_exists($product, 'addCustomOption')) {
                $product->addCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO, $serialized);
            }
        }
    }

    /**
     * Resolve configuration instance from quote item options or buyRequest.
     *
     * @param CartItemInterface $cartItem
     * @return ConfigurationInterface|null
     */
    private function resolveConfiguration(CartItemInterface $cartItem): ?ConfigurationInterface
    {
        return $this->resolveFromExtensionAttributes($cartItem)
            ?? $this->resolveFromQuoteOption($cartItem)
            ?? $this->resolveFromBuyRequest($cartItem);
    }

    /**
     * Resolve configuration from product option extension attributes.
     *
     * @param CartItemInterface $cartItem
     * @return ConfigurationInterface|null
     */
    private function resolveFromExtensionAttributes(CartItemInterface $cartItem): ?ConfigurationInterface
    {
        $productOption = $cartItem->getProductOption();
        if ($productOption instanceof ProductOptionInterface) {
            $extensionAttributes = $productOption->getExtensionAttributes();
            if ($extensionAttributes && method_exists($extensionAttributes, 'getAtelieConfiguracao')) {
                $existing = $extensionAttributes->getAtelieConfiguracao();
                if ($existing instanceof ConfigurationInterface) {
                    return $existing;
                }
            }
        }

        return null;
    }

    /**
     * Resolve configuration from quote item custom option.
     *
     * @param CartItemInterface $cartItem
     * @return ConfigurationInterface|null
     */
    private function resolveFromQuoteOption(CartItemInterface $cartItem): ?ConfigurationInterface
    {
        if (method_exists($cartItem, 'getOptionByCode')) {
            $option = $cartItem->getOptionByCode(Atelie::CUSTOM_OPTION_CONFIGURACAO);
            if ($option && $option->getValue()) {
                $val = $option->getValue();
                if (is_string($val)) {
                    try {
                        return $this->configurationSerializer->unserialize($val);
                    } catch (Exception $e) {
                        unset($e);
                    }
                }
            }
        }

        if (method_exists($cartItem, 'getProduct') && $cartItem->getProduct()) {
            $prodOption = $cartItem->getProduct()->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);
            if ($prodOption && $prodOption->getValue()) {
                $val = $prodOption->getValue();
                if (is_string($val)) {
                    try {
                        return $this->configurationSerializer->unserialize($val);
                    } catch (Exception $e) {
                        unset($e);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Resolve configuration from buyRequest DataObject.
     *
     * @param CartItemInterface $cartItem
     * @return ConfigurationInterface|null
     */
    private function resolveFromBuyRequest(CartItemInterface $cartItem): ?ConfigurationInterface
    {
        if (method_exists($cartItem, 'getBuyRequest')) {
            $buyRequest = $cartItem->getBuyRequest();
            if ($buyRequest instanceof DataObject) {
                return $this->buyRequestReader->read($buyRequest);
            }
        }

        return null;
    }
}
