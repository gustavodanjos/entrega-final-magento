<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model\Product\Type\Atelie;

use Magento\Framework\DataObject;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;
use Nuvem\Atelie\Api\Data\SelectionInterface;
use Nuvem\Atelie\Model\Configuration;
use Nuvem\Atelie\Model\ConfigurationFactory;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Model\Selection;
use Nuvem\Atelie\Model\SelectionFactory;

/**
 * Parses and extracts configuration objects from buy requests.
 */
class BuyRequestReader
{
    /**
     * Keys to ignore when parsing flat buy requests
     */
    private const IGNORED_FLAT_KEYS = [
        'tamanho', 'size', 'gravacao', 'engraving',
        'qty', 'uenc', 'form_key', 'reset_cart', 'product'
    ];

    /**
     * @param ConfigurationFactory|null $configurationFactory
     * @param SelectionFactory|null $selectionFactory
     */
    public function __construct(
        private readonly ?ConfigurationFactory $configurationFactory = null,
        private readonly ?SelectionFactory $selectionFactory = null
    ) {
    }

    /**
     * Extract configuration instance from buyRequest.
     *
     * @param DataObject $buyRequest
     * @return ConfigurationInterface|null
     */
    public function read(DataObject $buyRequest): ?ConfigurationInterface
    {
        $atelieData = $buyRequest->getData('atelie')
            ?? $buyRequest->getData(Atelie::CUSTOM_OPTION_CONFIGURACAO);

        if ($atelieData === null) {
            return null;
        }

        if ($atelieData instanceof ConfigurationInterface) {
            return $atelieData;
        }

        if ($atelieData instanceof DataObject) {
            $atelieData = $atelieData->getData();
        }

        if (is_string($atelieData)) {
            $decoded = json_decode($atelieData, true);
            if (is_array($decoded)) {
                $atelieData = $decoded;
            }
        }

        return is_array($atelieData) ? $this->buildFromArray($atelieData) : null;
    }

    /**
     * Build ConfigurationInterface from array data.
     *
     * @param array $data
     * @return ConfigurationInterface
     */
    private function buildFromArray(array $data): ConfigurationInterface
    {
        $size = (int) ($data['tamanho'] ?? $data['size'] ?? 0);
        $engraving = isset($data['gravacao']) || isset($data['engraving'])
            ? (string) ($data['gravacao'] ?? $data['engraving'])
            : null;

        $rawSelections = $data['selecoes'] ?? $data['selections'] ?? null;
        $selections = [];

        if (is_array($rawSelections)) {
            $selections = $this->parseExplicitSelections($rawSelections);
        } else {
            $selections = $this->parseFlatSelections($data);
        }

        return $this->createConfiguration($size, $engraving, $selections);
    }

    /**
     * Parse explicit selections list.
     *
     * @param array $raw
     * @return SelectionInterface[]
     */
    private function parseExplicitSelections(array $raw): array
    {
        $selections = [];
        foreach ($raw as $key => $item) {
            if ($item instanceof SelectionInterface) {
                $selections[] = $item;
            } elseif (is_array($item)) {
                $grp = (string) ($item['grupo'] ?? $item['group'] ?? (is_string($key) ? $key : ''));
                $comp = (string) ($item['componente'] ?? $item['component'] ?? '');
                if ($grp !== '' && $comp !== '') {
                    $selections[] = $this->createSelection($grp, $comp);
                }
            } elseif (is_string($item) && is_string($key)) {
                $selections[] = $this->createSelection($key, $item);
            }
        }
        return $selections;
    }

    /**
     * Parse flat key-value pairs as selections.
     *
     * @param array $data
     * @return SelectionInterface[]
     */
    private function parseFlatSelections(array $data): array
    {
        $selections = [];
        foreach ($data as $key => $value) {
            if (!in_array($key, self::IGNORED_FLAT_KEYS, true) && is_string($value) && $value !== '') {
                $selections[] = $this->createSelection((string) $key, $value);
            }
        }
        return $selections;
    }

    /**
     * Create Selection instance.
     *
     * @param string $group
     * @param string $component
     * @return SelectionInterface
     */
    private function createSelection(string $group, string $component): SelectionInterface
    {
        return $this->selectionFactory !== null
            ? $this->selectionFactory->create(['group' => $group, 'component' => $component])
            : new Selection($group, $component);
    }

    /**
     * Create Configuration instance.
     *
     * @param int $size
     * @param string|null $engraving
     * @param SelectionInterface[] $selections
     * @return ConfigurationInterface
     */
    private function createConfiguration(int $size, ?string $engraving, array $selections): ConfigurationInterface
    {
        return $this->configurationFactory !== null
            ? $this->configurationFactory->create([
                'size' => $size,
                'engraving' => $engraving,
                'selections' => $selections,
            ])
            : new Configuration($size, $engraving, $selections);
    }
}
