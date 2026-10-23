<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Production;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Api\GroupRepositoryInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class SnapshotBuilder
{
    public function __construct(
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly Json $serializer
    ) {
    }

    public function build(OrderInterface $order, OrderItemInterface $item): array
    {
        $options = $item->getProductOptions() ?? [];
        $configuration = $options[Atelie::CUSTOM_OPTION_CONFIGURACAO]
            ?? $options['configuracao']
            ?? null;

        if (is_string($configuration)) {
            $configuration = $this->serializer->unserialize($configuration);
        }
        if (!is_array($configuration)) {
            throw new LocalizedException(
                __('O item %1 não possui configuração do ateliê no pedido.', $item->getItemId())
            );
        }

        $selections = $configuration['selecoes'] ?? $configuration['selections'] ?? [];
        $size = (int) ($configuration['tamanho'] ?? $configuration['size'] ?? 0);
        if ($size <= 0 || !is_array($selections) || $selections === []) {
            throw new LocalizedException(
                __('A configuração do item %1 está incompleta.', $item->getItemId())
            );
        }

        $components = [];
        $groups = [];
        foreach ($selections as $selection) {
            if (!is_array($selection)) {
                throw new LocalizedException(__('Seleção de componente inválida na ficha.'));
            }

            $groupCode = (string) ($selection['grupo'] ?? $selection['group'] ?? '');
            $componentCode = (string) ($selection['componente'] ?? $selection['component'] ?? '');
            if ($groupCode === '' || $componentCode === '' || isset($groups[$groupCode])) {
                throw new LocalizedException(__('Grupo ou componente inválido na ficha.'));
            }
            $group = $this->groupRepository->getByCodigo($groupCode);
            $component = $this->componentRepository->getByCodigo($componentCode);
            if ((int) $component->getGrupoId() !== (int) $group->getGrupoId()) {
                throw new LocalizedException(__('O componente da ficha não pertence ao grupo informado.'));
            }
            $groups[$groupCode] = true;
            $components[] = [
                'grupo' => $groupCode,
                'grupo_nome' => $group->getNome(),
                'componente' => $componentCode,
                'nome' => $component->getNome(),
                'cor_hex' => $component->getCorHex(),
                'acabamento' => $component->getAcabamento(),
            ];
        }

        return [
            'order_id' => (int) $order->getEntityId(),
            'order_increment_id' => (string) $order->getIncrementId(),
            'order_item_id' => (int) $item->getItemId(),
            'store_id' => (int) $order->getStoreId(),
            'sku' => (string) $item->getSku(),
            'nome' => (string) $item->getName(),
            'quantidade' => (float) $item->getQtyOrdered(),
            'tamanho' => $size,
            'gravacao' => $configuration['gravacao'] ?? $configuration['engraving'] ?? null,
            'selecoes' => $components,
        ];
    }
}
