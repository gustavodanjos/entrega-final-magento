<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Model\Resolver;

use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Nuvem\Atelie\Api\ConfigurationValidatorInterface;
use Nuvem\Atelie\Api\PriceCalculatorInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie\BuyRequestReader;

class Validar implements ResolverInterface
{
    public function __construct(
        private readonly DataObjectFactory $dataObjectFactory,
        private readonly BuyRequestReader $buyRequestReader,
        private readonly ConfigurationValidatorInterface $validator,
        private readonly PriceCalculatorInterface $priceCalculator
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
        $input = $args['configuracao'] ?? null;

        if ($sku === '' || !is_array($input)) {
            throw new GraphQlInputException(
                __('SKU and configuration are required.')
            );
        }

        $buyRequest = $this->dataObjectFactory->create([
            'atelie' => $input
        ]);

        $configuration = $this->buyRequestReader->read($buyRequest);

        if ($configuration === null) {
            throw new GraphQlInputException(
                __('Invalid Atelie configuration.')
            );
        }

        try {
            $result = $this->validator->validate(
                $sku,
                $configuration
            );

            if (!$result->isValid()) {
                return [
                    'valido' => false,
                    'preco' => null,
                    'erros' => array_map(
                        static fn (string $message): array => [
                            'regra' => null,
                            'grupo' => null,
                            'mensagem' => $message
                        ],
                        $result->getMessages()
                    )
                ];
            }

            $storeId = (int) $context
                ->getExtensionAttributes()
                ->getStore()
                ->getId();

            $price = $this->priceCalculator->calculate(
                $sku,
                $configuration,
                $storeId
            );

            return [
                'valido' => true,
                'preco' => [
                    'value' => $price,
                    'currency' => 'USD'
                ],
                'erros' => []
            ];
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(
                __('Atelie product "%1" was not found.', $sku),
                $e
            );
        }
    }
}
