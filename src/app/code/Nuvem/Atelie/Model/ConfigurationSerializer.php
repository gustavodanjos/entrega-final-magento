<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Model;

use JsonException;
use Nuvem\Atelie\Api\Data\ConfigurationInterface;

/**
 * Normalizes a configuration and serializes it in the contract format.
 *
 * Normalization: engraving is trimmed (empty becomes null), selections are
 * ordered by group and duplicated groups are rejected. Two equal configurations
 * written in a different order therefore produce exactly the same string.
 */
class ConfigurationSerializer
{
    /**
     * Serializes a configuration object to a normalized JSON string.
     *
     * @param ConfigurationInterface $configuration
     * @return string
     * @throws \InvalidArgumentException when the same group is selected twice
     * @throws JsonException when the configuration cannot be encoded
     */
    public function serialize(ConfigurationInterface $configuration): string
    {
        $selections = [];
        foreach ($configuration->getSelections() as $selection) {
            $group = (string) ($selection->getGrupo() ?: $selection->getGroup());
            $component = (string) ($selection->getComponente() ?: $selection->getComponent());
            if (isset($selections[$group])) {
                throw new \InvalidArgumentException(
                    sprintf('The group "%s" is selected more than once.', $group)
                );
            }
            $selections[$group] = [
                'grupo' => $group,
                'componente' => $component,
            ];
        }
        ksort($selections);

        $engraving = $configuration->getGravacao() ?? $configuration->getEngraving();
        if ($engraving !== null) {
            $engraving = trim((string) $engraving);
            if ($engraving === '') {
                $engraving = null;
            }
        }

        $size = (int) ($configuration->getTamanho() ?: $configuration->getSize());

        return json_encode(
            [
                'tamanho' => $size,
                'gravacao' => $engraving,
                'selecoes' => array_values($selections),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }

    /**
     * Reconstructs a configuration from contract-format JSON string.
     *
     * @param string $json
     * @return ConfigurationInterface
     * @throws \InvalidArgumentException when the string is not valid contract-format JSON
     */
    public function unserialize(string $json): ConfigurationInterface
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException(
                'The configuration payload is not valid JSON.',
                0,
                $exception
            );
        }

        if (!is_array($data)
            || !array_key_exists('tamanho', $data)
            || !array_key_exists('gravacao', $data)
            || !array_key_exists('selecoes', $data)
            || !is_int($data['tamanho'])
            || ($data['gravacao'] !== null && !is_string($data['gravacao']))
            || !is_array($data['selecoes'])
        ) {
            throw new \InvalidArgumentException(
                'The configuration payload does not match the contract format.'
            );
        }

        $selections = [];
        foreach ($data['selecoes'] as $selection) {
            if (!is_array($selection)
                || !isset($selection['grupo'], $selection['componente'])
                || !is_string($selection['grupo'])
                || !is_string($selection['componente'])
            ) {
                throw new \InvalidArgumentException(
                    'The configuration payload does not match the contract format.'
                );
            }
            if (isset($selections[$selection['grupo']])) {
                throw new \InvalidArgumentException(
                    sprintf('The group "%s" is selected more than once.', $selection['grupo'])
                );
            }
            $selections[$selection['grupo']] = new Selection(
                $selection['grupo'],
                $selection['componente']
            );
        }

        return new Configuration($data['tamanho'], $data['gravacao'], array_values($selections));
    }
}
