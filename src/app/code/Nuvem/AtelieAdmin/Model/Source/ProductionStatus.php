<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ProductionStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'fila', 'label' => __('Na fila')],
            ['value' => 'produzindo', 'label' => __('Produzindo')],
            ['value' => 'pronta', 'label' => __('Pronta')],
            ['value' => 'enviada', 'label' => __('Enviada')]
        ];
    }

    public function isValid(string $status): bool
    {
        return in_array($status, array_column($this->toOptionArray(), 'value'), true);
    }

    public function getLabel(string $status): string
    {
        foreach ($this->toOptionArray() as $option) {
            if ($option['value'] === $status) {
                return (string) $option['label'];
            }
        }
        return $status;
    }
}
