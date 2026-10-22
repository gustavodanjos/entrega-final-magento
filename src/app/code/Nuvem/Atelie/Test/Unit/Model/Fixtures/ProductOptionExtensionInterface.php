<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Magento\Quote\Api\Data;

use Magento\Framework\Api\ExtensionAttributesInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;

if (!interface_exists(ProductOptionExtensionInterface::class, false)) {
    /**
     * Test fixture for ProductOptionExtensionInterface in isolated unit tests.
     */
    interface ProductOptionExtensionInterface extends ExtensionAttributesInterface
    {
        /**
         * Get atelie configuracao.
         *
         * @return ConfigurationInterface|null
         */
        public function getAtelieConfiguracao();

        /**
         * Set atelie configuracao.
         *
         * @param ConfigurationInterface $atelieConfiguracao
         * @return $this
         */
        public function setAtelieConfiguracao(ConfigurationInterface $atelieConfiguracao);
    }
}
