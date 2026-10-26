<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Production;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Nuvem\AtelieAdmin\Model\ResourceModel\Production;

class SheetProvider
{
    public function __construct(
        private readonly Production $resource,
        private readonly Json $serializer
    ) {
    }

    public function getById(int $id): array
    {
        $sheet = $id > 0 ? $this->resource->findById($id) : null;
        if ($sheet === null) {
            throw new NoSuchEntityException(__('A ficha de produção não existe.'));
        }
        try {
            $snapshot = $this->serializer->unserialize((string) $sheet['conteudo']);
        } catch (\InvalidArgumentException $exception) {
            throw new LocalizedException(__('Não foi possível ler o conteúdo da ficha.'), $exception);
        }
        if (!is_array($snapshot) || !is_array($snapshot['selecoes'] ?? null)) {
            throw new LocalizedException(__('O conteúdo da ficha é inválido.'));
        }
        $sheet['snapshot'] = $snapshot;
        return $sheet;
    }

    public function getConfiguration(array $snapshot): string
    {
        $selections = [];
        foreach ($snapshot['selecoes'] as $selection) {
            if (!is_array($selection) || !isset($selection['grupo'], $selection['componente'])) {
                throw new LocalizedException(__('A configuração da ficha é inválida.'));
            }
            $selections[] = [
                'grupo' => (string) $selection['grupo'],
                'componente' => (string) $selection['componente']
            ];
        }
        return $this->serializer->serialize([
            'tamanho' => (int) ($snapshot['tamanho'] ?? 0),
            'gravacao' => $snapshot['gravacao'] ?? null,
            'selecoes' => $selections
        ]);
    }
}
