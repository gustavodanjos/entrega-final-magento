<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Source\Rule;

use Nuvem\Atelie\Api\Data\ComponentInterface;

class ComponentCodes extends Components
{
    protected function getOptionValue(
        ComponentInterface $component
    ): string {
        return (string) $component->getCodigo();
    }
}