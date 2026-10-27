<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Model\Resolver;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Nuvem\AtelieSales\Api\PublicAvailabilityInterface;

class Disponibilidade implements ResolverInterface
{
    public function __construct(
        private readonly PublicAvailabilityInterface $availability
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
            throw new GraphQlInputException(
                __('Informe o SKU do produto.')
            );
        }

        try {
            return $this->availability->getBySku($sku);
        } catch (NoSuchEntityException $exception) {
            throw new GraphQlNoSuchEntityException(
                __('Produto do Ateliê não encontrado: %1', $sku),
                $exception
            );
        } catch (InputException $exception) {
            throw new GraphQlInputException(
                __('O SKU informado não pertence a um produto do Ateliê.'),
                $exception
            );
        }
    }
}
