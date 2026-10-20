<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Product\Type\Atelie;

use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\Atelie\Model\Product\Type\Atelie;

/**
 * Handles validation, serialization, and option persistence for Atelie product configurations.
 */
class ConfigurationHandler
{
    /**
     * @param BuyRequestReader $buyRequestReader
     * @param ConfigurationValidatorInterface $configurationValidator
     * @param ConfigurationSerializer $configurationSerializer
     */
    public function __construct(
        private readonly BuyRequestReader $buyRequestReader,
        private readonly ConfigurationValidatorInterface $configurationValidator,
        private readonly ConfigurationSerializer $configurationSerializer
    ) {
    }

    /**
     * Process and attach configuration to product. Returns error string or true.
     *
     * @param DataObject $buyRequest
     * @param Product $product
     * @param bool $isStrict
     * @param Phrase|string $specifyOptionMessage
     * @return true|string
     */
    public function prepare(
        DataObject $buyRequest,
        Product $product,
        bool $isStrict,
        Phrase|string $specifyOptionMessage
    ): bool|string {
        $configuration = $this->buyRequestReader->read($buyRequest);
        if ($configuration === null) {
            return $isStrict ? (string) $specifyOptionMessage : true;
        }

        $sku = (string) ($product->getSku() ?: $product->getData('sku'));
        try {
            $validation = $this->configurationValidator->validate($sku, $configuration);
        } catch (NoSuchEntityException|LocalizedException $e) {
            return $isStrict ? $e->getMessage() : true;
        }

        if (!$validation->isValid() && $isStrict) {
            $messages = $validation->getMessages();
            return !empty($messages) ? implode("\n", $messages) : (string) $specifyOptionMessage;
        }

        $serialized = $this->configurationSerializer->serialize($configuration);
        $product->addCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO, $serialized);
        return true;
    }

    /**
     * Read configuration from buyRequest.
     *
     * @param DataObject $buyRequest
     * @return ConfigurationInterface|null
     */
    public function read(DataObject $buyRequest): ?ConfigurationInterface
    {
        return $this->buyRequestReader->read($buyRequest);
    }
}
