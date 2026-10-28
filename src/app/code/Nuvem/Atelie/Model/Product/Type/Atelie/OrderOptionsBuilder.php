<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Product\Type\Atelie;

use Magento\Catalog\Model\Product;
use Magento\Framework\Serialize\Serializer\Json as JsonSerializer;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Psr\Log\LoggerInterface;

/**
 * Builds structured and human-readable order options for Atelie products.
 */
class OrderOptionsBuilder
{
    public const SNAPSHOT_KEY = 'atelie_ficha';

    /**
     * @var array<string, string>
     */
    private array $groupLabels = [];

    /**
     * @var array<string, string>
     */
    private array $componentLabels = [];

    /**
     * @var array<string, string|null>
     */
    private array $componentColors = [];

    /**
     * @param GroupRepositoryInterface $groupRepository
     * @param ComponentRepositoryInterface $componentRepository
     * @param JsonSerializer $serializer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly JsonSerializer $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Build options array with configuration and readable label/value list.
     *
     * @param Product $product
     * @param array $options
     * @return array
     */
    public function build(Product $product, array $options = []): array
    {
        $configData = $this->extractConfigData($product);
        if ($configData === null) {
            return $options;
        }

        $options[Atelie::CUSTOM_OPTION_CONFIGURACAO] = $configData;
        $options['configuracao'] = $configData;

        $snapshot = $this->buildSnapshot($configData);
        $readable = array_map(
            static fn (array $row): array => ['label' => $row['label'], 'value' => $row['value']],
            $snapshot
        );
        if (!empty($readable)) {
            $options['attributes_info'] = $readable;
            $options['additional_options'] = $readable;
            $options[self::SNAPSHOT_KEY] = $snapshot;
        }

        return $options;
    }

    /**
     * Extract configuration data from product custom options.
     *
     * @param Product $product
     * @return array|null
     */
    private function extractConfigData(Product $product): ?array
    {
        $customOption = $product->getCustomOption(Atelie::CUSTOM_OPTION_CONFIGURACAO);
        if ($customOption) {
            $val = $customOption->getValue();
            if (is_array($val)) {
                return $val;
            }
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                return is_array($decoded) ? $decoded : null;
            }
        }

        $info = $product->getCustomOption('info_buyRequest');
        if ($info) {
            $buyRequest = $this->serializer->unserialize($info->getValue());
            if (is_array($buyRequest)) {
                $raw = $buyRequest['atelie'] ?? $buyRequest[Atelie::CUSTOM_OPTION_CONFIGURACAO] ?? null;
                if (is_array($raw)) {
                    return $raw;
                }
                if (is_string($raw)) {
                    $decoded = json_decode($raw, true);
                    return is_array($decoded) ? $decoded : null;
                }
            }
        }

        return null;
    }

    /**
     * Build display data at purchase time, including the original component colors.
     *
     * @param array $configData
     * @return array<int, array{label: string, value: string, grupo?: string, componente?: string, cor_hex?: ?string}>
     */
    private function buildSnapshot(array $configData): array
    {
        $readable = [];

        if (isset($configData['tamanho'])) {
            $readable[] = ['label' => (string) __('Tamanho'), 'value' => (string) $configData['tamanho']];
        }

        if (isset($configData['gravacao']) && trim((string) $configData['gravacao']) !== '') {
            $readable[] = ['label' => (string) __('Gravação'), 'value' => (string) $configData['gravacao']];
        }

        $selections = $configData['selecoes'] ?? $configData['selections'] ?? [];
        if (is_array($selections)) {
            foreach ($selections as $sel) {
                $grp = (string) ($sel['grupo'] ?? $sel['group'] ?? '');
                $cmp = (string) ($sel['componente'] ?? $sel['component'] ?? '');
                if ($grp !== '') {
                    $readable[] = [
                        'label' => $this->getGroupLabel($grp),
                        'value' => $this->getComponentLabel($cmp),
                        'grupo' => $grp,
                        'componente' => $cmp,
                        'cor_hex' => $this->componentColors[$cmp] ?? null,
                    ];
                }
            }
        }

        return $readable;
    }

    /**
     * Get readable group label.
     *
     * @param string $groupCode
     * @return string
     */
    private function getGroupLabel(string $groupCode): string
    {
        if (isset($this->groupLabels[$groupCode])) {
            return $this->groupLabels[$groupCode];
        }

        try {
            $group = $this->groupRepository->getByCodigo($groupCode);
            if ($group->getNome()) {
                return $this->groupLabels[$groupCode] = (string) $group->getNome();
            }
        } catch (\Exception $e) {
            $this->logger->debug('Error loading atelie group label: ' . $e->getMessage());
        }

        return $this->groupLabels[$groupCode] = ucfirst(str_replace('_', ' ', $groupCode));
    }

    /**
     * Get readable component label.
     *
     * @param string $compCode
     * @return string
     */
    private function getComponentLabel(string $compCode): string
    {
        if (isset($this->componentLabels[$compCode])) {
            return $this->componentLabels[$compCode];
        }

        try {
            $comp = $this->componentRepository->getByCodigo($compCode);
            $this->componentColors[$compCode] = $comp->getCorHex();
            if ($comp->getNome()) {
                return $this->componentLabels[$compCode] = (string) $comp->getNome();
            }
        } catch (\Exception $e) {
            $this->logger->debug('Error loading atelie component label: ' . $e->getMessage());
        }

        return $this->componentLabels[$compCode] = $compCode;
    }
}
