<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Test\Unit\Model;

use Magento\Quote\Api\Data\ProductOptionExtensionInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;

/**
 * Stub interface for ProductOptionExtensionInterface in unit tests.
 */
interface ProductOptionExtensionStubInterface extends ProductOptionExtensionInterface
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
