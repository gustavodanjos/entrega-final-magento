<?php
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\AtelieSales\Model\QuoteItemConfigurationReader;

final class CheckoutFixture
{
    public function __construct(private readonly ObjectManagerInterface $objectManager)
    {
    }

    public function create(array $payload): int
    {
        assertStockScenario(is_string($payload['sku'] ?? null), 'Fixture requires a SKU.');
        assertStockScenario(($payload['qty'] ?? null) === 1, 'Fixture requires quantity one.');
        assertStockScenario(count($payload['configuracao']['selecoes'] ?? []) === 7, 'Fixture requires seven selections.');
        $store = $this->objectManager->get(StoreManagerInterface::class)->getStore();
        $quotes = $this->objectManager->get(CartRepositoryInterface::class);
        $quoteId = (int) $this->objectManager->get(CartManagementInterface::class)->createEmptyCart();
        $quote = $quotes->get($quoteId);
        $quote->setStore($store)->setCustomerIsGuest(true)->setCustomerEmail('f23-integration@example.test');
        $quote->setCustomerGroupId(0);
        $product = $this->objectManager->get(ProductRepositoryInterface::class)
            ->get($payload['sku'], false, (int) $store->getId(), true);
        $item = $quote->addProduct($product, new DataObject([
            'qty' => 1,
            'atelie' => $payload['configuracao'],
        ]));
        assertStockScenario(!is_string($item), is_string($item) ? $item : 'Cannot add fixture product.');
        $region = $this->objectManager->get(RegionFactory::class)->create()->loadByCode('SP', 'BR');
        $address = [
            'firstname' => 'F23', 'lastname' => 'Integration', 'street' => ['Rua das Nuvens, 100'],
            'city' => 'São Paulo', 'region' => 'São Paulo', 'region_id' => (int) $region->getId(),
            'postcode' => '01310-100', 'country_id' => 'BR', 'telephone' => '11999990000',
            'email' => 'f23-integration@example.test',
        ];
        $quote->getBillingAddress()->addData($address);
        $shipping = $quote->getShippingAddress();
        $shipping->addData($address)->setCollectShippingRates(true);
        $shipping->collectShippingRates();
        assertStockScenario((bool) $shipping->getShippingRateByCode('flatrate_flatrate'), 'Enable flatrate in isolated store.');
        $shipping->setShippingMethod('flatrate_flatrate');
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $quote->collectTotals();
        $quotes->save($quote);
        $this->assertPersisted($quoteId, $payload);

        return $quoteId;
    }

    private function assertPersisted(int $quoteId, array $payload): void
    {
        // A fresh repository avoids proving persistence with the same in-memory quote.
        $quote = $this->objectManager->create(CartRepositoryInterface::class)->get($quoteId);
        $items = $quote->getAllVisibleItems();
        assertStockScenario(count($items) === 1, 'Prepared quote must contain one persisted item.');
        $demand = $this->objectManager->get(QuoteItemConfigurationReader::class)->getComponentQuantities($items[0]);
        $expected = array_fill_keys(array_column($payload['configuracao']['selecoes'], 'componente'), 1);
        ksort($demand);
        ksort($expected);
        assertStockScenario($demand === $expected, 'Persisted configuration differs from fixture.');
    }

    public function stocks(array $codes): array
    {
        $resource = $this->objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from($resource->getTableName('nuvem_atelie_componente'), ['codigo', 'estoque'])
            ->where('codigo IN (?)', $codes)
            ->order('codigo');

        return array_map('intval', $connection->fetchPairs($select));
    }
    public function movements(array $quoteIds): array
    {
        $resource = $this->objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from(
                ['m' => $resource->getTableName('nuvem_atelie_movimentacao')],
                ['movimentacao_id', 'quote_id', 'tipo', 'quantidade']
            )
            ->join(
                ['c' => $resource->getTableName('nuvem_atelie_componente')],
                'c.componente_id = m.componente_id',
                ['codigo']
            )
            ->where('m.quote_id IN (?)', $quoteIds)
            ->order('m.movimentacao_id');

        return $connection->fetchAll($select);
    }
}
