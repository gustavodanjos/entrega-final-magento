<?php
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
     * @throws \InvalidArgumentException when the same group is selected twice
     * @throws JsonException when the configuration cannot be encoded
     */
    public function serialize(ConfigurationInterface $configuration): string
    {
        $selections = [];
        foreach ($configuration->getSelections() as $selection) {
            $group = $selection->getGroup();
            if (isset($selections[$group])) {
                throw new \InvalidArgumentException(
                    sprintf('The group "%s" is selected more than once.', $group)
                );
            }
            $selections[$group] = [
                'grupo' => $group,
                'componente' => $selection->getComponent(),
            ];
        }
        ksort($selections);

        $engraving = $configuration->getEngraving();
        if ($engraving !== null) {
            $engraving = trim($engraving);
            if ($engraving === '') {
                $engraving = null;
            }
        }

        return json_encode(
            [
                'tamanho' => $configuration->getSize(),
                'gravacao' => $engraving,
                'selecoes' => array_values($selections),
            ],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
    }
}
