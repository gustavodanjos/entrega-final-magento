<?php
declare(strict_types=1);

namespace Nuvem\AtelieGraphQl\Model\Resolver;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DataObject\Factory as DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Exception\GraphQlNoSuchEntityException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteMutexInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Nuvem\Atelie\Model\Product\Type\Atelie;

class AddToCart implements ResolverInterface
{
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly QuoteMutexInterface $quoteMutex,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly DataObjectFactory $dataObjectFactory
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $input = $args['input'] ?? [];

        $cartId = trim((string) ($input['cart_id'] ?? ''));
        $sku = trim((string) ($input['sku'] ?? ''));
        $quantity = (float) ($input['quantity'] ?? 1);
        $configuration = $input['configuracao'] ?? null;

        if ($cartId === '' || $sku === '') {
            throw new GraphQlInputException(
                __('Cart ID and SKU are required.')
            );
        }

        if (!is_finite($quantity) || $quantity <= 0) {
            throw new GraphQlInputException(
                __('Quantity must be greater than zero.')
            );
        }

        if (!is_array($configuration)) {
            throw new GraphQlInputException(
                __('Atelie configuration is required.')
            );
        }

        return $this->quoteMutex->execute(
            [$cartId],
            \Closure::fromCallable([$this, 'addProduct']),
            [$context, $cartId, $sku, $quantity, $configuration]
        );
    }

    private function addProduct(
        $context,
        string $cartId,
        string $sku,
        float $quantity,
        array $configuration
    ): array {
        $storeId = (int) $context
            ->getExtensionAttributes()
            ->getStore()
            ->getId();

        $cart = $this->getCartForUser->execute(
            $cartId,
            $context->getUserId(),
            $storeId
        );

        try {
            $product = $this->productRepository->get(
                $sku,
                false,
                $storeId,
                true
            );
        } catch (NoSuchEntityException $e) {
            throw new GraphQlNoSuchEntityException(
                __('Product with SKU "%1" was not found.', $sku),
                $e
            );
        }

        if ($product->getTypeId() !== Atelie::TYPE_CODE) {
            throw new GraphQlInputException(
                __('Product "%1" is not an Atelie product.', $sku)
            );
        }

        $buyRequest = $this->dataObjectFactory->create([
            'qty' => $quantity,
            'atelie' => $configuration
        ]);

        try {
            // O tipo de produto Atelie executa a validacao
            // pelo ConfigurationHandler do nucleo.
            $result = $cart->addProduct($product, $buyRequest);

            if (is_string($result)) {
                throw new GraphQlInputException(__($result));
            }

            $this->cartRepository->save($cart);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(
                __('Could not add Atelie product: %1', $e->getMessage()),
                $e
            );
        }

        return [
            'cart' => [
                'model' => $cart
            ]
        ];
    }
}
