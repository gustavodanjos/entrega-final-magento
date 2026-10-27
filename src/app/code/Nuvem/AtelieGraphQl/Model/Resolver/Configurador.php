<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Model\Resolver;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Api\AtelieCatalogInterface;

class Configurador implements ResolverInterface
{
    public function __construct(
        private readonly AtelieCatalogInterface $catalog,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $sku = trim((string) ($args['sku'] ?? ''));

        if ($sku === '') {
            throw new GraphQlInputException(__('Informe o SKU do produto.'));
        }

        try {
            $data = $this->catalog->getBySku($sku);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Produto do Ateliê não encontrado: %1', $sku),
                $exception
            );
        }

        $currency = (string) $this->storeManager
            ->getStore()
            ->getCurrentCurrencyCode();

        $data['preco_base'] = $this->toMoney(
            $data['preco_base'],
            $currency
        );

        if (is_array($data['gravacao'] ?? null)) {
            $data['gravacao']['preco'] = $this->toMoney(
                $data['gravacao']['preco'],
                $currency
            );
        }

        foreach ($data['grupos'] as $groupIndex => $group) {
            foreach ($group['componentes'] as $componentIndex => $component) {
                $data['grupos'][$groupIndex]['componentes'][$componentIndex]['preco_adicional']
                    = $this->toMoney(
                        $component['preco_adicional'],
                        $currency
                    );
            }
        }

        return $data;
    }

    private function toMoney(
        float|int|string $amount,
        string $currency
    ): array {
        return [
            'value' => (float) $amount,
            'currency' => $currency
        ];
    }
}
