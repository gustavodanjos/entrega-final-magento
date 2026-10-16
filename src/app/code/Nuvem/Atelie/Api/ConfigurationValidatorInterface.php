<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Api;

use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;

/**
 * Single place in the system that decides whether a sneaker configuration can exist.
 *
 * Every purchase path (studio, cart, REST, GraphQL) goes through it.
 *
 * @api
 */
interface ConfigurationValidatorInterface
{
    /**
     * Validate a configuration for the given SKU.
     *
     * An invalid configuration is not an exception: it is a result with
     * isValid() false and every violation found in getMessages().
     *
     * On error the validator fails loudly instead of returning a misleading
     * result: an unknown SKU can never produce a valid configuration.
     *
     * @param string $sku
     * @param ConfigurationInterface $configuration
     * @return ValidationResultInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException when the SKU does not exist or is not an atelie product
     */
    public function validate(string $sku, ConfigurationInterface $configuration): ValidationResultInterface;
}
