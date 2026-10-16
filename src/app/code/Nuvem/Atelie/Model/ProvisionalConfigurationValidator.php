<?php
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\ValidationResultInterface;

/**
 * Provisional validator: accepts every configuration, so no front waits for the
 * real rules (F1.4).
 *
 * The catalog rules (R01 to R05), size range and engraving checks only enter in
 * the real implementation.
 */
class ProvisionalConfigurationValidator implements ConfigurationValidatorInterface
{
    public function validate(
        string $sku,
        ConfigurationInterface $configuration
    ): ValidationResultInterface {
        return new ValidationResult(true, []);
    }
}
