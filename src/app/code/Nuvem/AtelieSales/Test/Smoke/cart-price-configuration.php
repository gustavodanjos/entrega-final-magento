<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Smoke;

use InvalidArgumentException;
use RuntimeException;
use Magento\Authorization\Model\CompositeUserContext;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Quote\Api\CartItemRepositoryInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Cart\Totals\ItemConverter;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;
use Nuvem\AtelieSales\Model\Cart\OptionListBuilder;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    return;
}

require dirname(__DIR__, 6) . '/app/bootstrap.php';

$area = $argv[1] ?? 'frontend';
$identity = $argv[3] ?? '';
if (!in_array($area, ['frontend', 'webapi_rest'], true)
    || ($argv[2] ?? '') !== '--run'
    || !in_array($identity, ['guest', 'customer'], true)
) {
    throw new InvalidArgumentException('Use frontend|webapi_rest --run guest|customer on a disposable local database.');
}

$objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$objectManager->get(State::class)->setAreaCode($area);
$objectManager->configure($objectManager->get(ConfigLoaderInterface::class)->load($area));

/**
 * The outer transaction rolls back customer, quote, mask and option fixtures together.
 * Separate repository instances ensure reload assertions do not read repository caches.
 */
class CartPriceConfigurationSmoke
{
    private const SKU = 'NUVEM-RUNNER';
    private const FULL_PRICE = 589.40;
    private const SPECIAL_PRICE = 544.41;
    private const UNENGRAVED_PRICE = 559.50;
    private const PRICE_TOLERANCE = 0.001;
    private const SPECIAL_BASE_PRICE = 404.91;
    private const SELECTIONS = [
        'cabedal' => 'CAB-TRICO-LAVANDA',
        'biqueira' => 'BIQ-LISA-CEU',
        'calcanhar' => 'CAL-ACOLCHOADO-CEU',
        'cadarco' => 'CAD-ELASTICO-MENTA',
        'emblema' => 'EMB-NUVEM-PESSEGO',
        'entressola' => 'ENT-EVA-NUVEM',
        'solado' => 'SOL-TRANSLUCIDO-GELO',
    ];

    public function __construct(private readonly ObjectManagerInterface $objectManager)
    {
    }

    public function run(bool $authenticated): void
    {
        $cartId = $this->createCart($authenticated);
        $this->addItem($cartId, 'LUIZ');
        $quote = $this->reload($cartId);
        $item = $quote->getAllVisibleItems()[0];
        $serialized = (string)$item->getOptionByCode('atelie_configuracao')->getValue();
        $serializer = $this->objectManager->get(ConfigurationSerializer::class);
        $this->check(
            $serialized === $serializer->serialize($serializer->unserialize($serialized)),
            'Stored configuration must be canonical.'
        );
        $configuration = $serializer->unserialize($serialized);
        $selections = [];
        foreach ($configuration->getSelections() as $selection) {
            $selections[$selection->getGroup()] = $selection->getComponent();
        }
        $expectedSelections = self::SELECTIONS;
        ksort($expectedSelections);
        $this->check($selections === $expectedSelections, 'Reload changed selected components.');
        $this->check($configuration->getEngraving() === 'LUIZ', 'Reload changed engraving.');
        $this->verify($item, self::FULL_PRICE, 1, 9);
        $this->check(
            (int)$quote->getCustomerId() === (int)$this->objectManager->get(CustomerSession::class)->getCustomerId(),
            'Reload must preserve customer ownership.'
        );

        $this->addItem($cartId, 'LUIZ');
        $quote = $this->reload($cartId);
        $this->check(count($quote->getAllVisibleItems()) === 1, 'Equal configurations must merge.');
        $this->verify($quote->getAllVisibleItems()[0], self::FULL_PRICE, 2, 9);

        $this->addItem($cartId, null);
        $quote = $this->reload($cartId);
        $this->check(count($quote->getAllVisibleItems()) === 2, 'Different configurations need distinct lines.');
        foreach ($quote->getAllVisibleItems() as $line) {
            $configuration = $serializer->unserialize($line->getOptionByCode('atelie_configuracao')->getValue());
            $engraved = $configuration->getEngraving() !== null;
            $this->verifyConfigurationLine($line, $engraved, 2);
            $line->setQty($engraved ? 3 : 1);
        }
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        $this->objectManager->get(CartRepositoryInterface::class)->save($quote);
        $quote = $this->reload($cartId);
        foreach ($quote->getAllVisibleItems() as $line) {
            $engraved = $serializer->unserialize($line->getOptionByCode('atelie_configuracao')->getValue())
                ->getEngraving() !== null;
            $this->verifyConfigurationLine($line, $engraved, 3);
        }

        $this->verifySpecialPrice($cartId);
        fwrite(
            STDOUT,
            ($authenticated ? 'customer' : 'guest')
                . ': persisted configuration, native price, quantity, line identity and special price OK.' . PHP_EOL
        );
    }

    private function createCart(bool $authenticated): int
    {
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $store = $storeManager->getDefaultStoreView();
        $storeManager->setCurrentStore($store);
        $this->check($store->getCurrentCurrencyCode() === 'BRL', 'The fixture requires BRL.');
        $account = null;
        if ($authenticated) {
            $account = $this->objectManager->get(CustomerInterfaceFactory::class)->create();
            $account->setWebsiteId((int)$store->getWebsiteId())
                ->setEmail('f22-' . bin2hex(random_bytes(8)) . '@example.invalid')
                ->setFirstname('F22')->setLastname('Smoke');
            $account = $this->objectManager->get(CustomerRepositoryInterface::class)->save($account);
        }

        // REST accepts session authentication only for AJAX requests, as used by checkout.
        $this->objectManager->get(RequestInterface::class)->getHeaders()
            ->addHeaderLine('X-Requested-With', 'XMLHttpRequest');
        $this->objectManager->get(CustomerSession::class)->setCustomerId($account?->getId());
        $this->objectManager->get(CompositeUserContext::class)->_resetState();
        $management = $this->objectManager->get(CartManagementInterface::class);
        return (int)($account === null
            ? $management->createEmptyCart()
            : $management->createEmptyCartForCustomer((int)$account->getId()));
    }

    private function verifySpecialPrice(int $cartId): void
    {
        $product = $this->objectManager->get(ProductRepositoryInterface::class)->get(self::SKU, false, null, true);
        $resource = $this->objectManager->get(ProductResource::class);
        $specialAttributes = [
            'special_price' => self::SPECIAL_BASE_PRICE,
            'special_from_date' => null,
            'special_to_date' => null,
        ];
        foreach ($specialAttributes as $code => $value) {
            $product->setData($code, $value);
            $resource->saveAttribute($product, $code);
        }
        $quote = $this->reload($cartId);
        $this->verify($quote->getAllVisibleItems()[0], self::SPECIAL_PRICE, 3, 9);
    }

    private function addItem(int $cartId, ?string $engraving): void
    {
        $selections = [];
        foreach (self::SELECTIONS as $group => $component) {
            $selections[] = ['grupo' => $group, 'componente' => $component];
        }
        $input = $this->objectManager->get(ServiceInputProcessor::class)->process(
            CartItemRepositoryInterface::class,
            'save',
            ['cartItem' => [
                'quote_id' => $cartId,
                'sku' => self::SKU,
                'qty' => 1,
                'price' => 1,
                'custom_price' => 1,
                'product_option' => ['extension_attributes' => ['atelie_configuracao' => [
                    'tamanho' => 38,
                    'gravacao' => $engraving,
                    'selecoes' => $selections,
                ]]],
            ]]
        );
        $this->objectManager->create(CartItemRepositoryInterface::class)->save($input[0]);
    }

    private function reload(int $cartId): Quote
    {
        $quote = $this->objectManager->create(CartRepositoryInterface::class)->get($cartId);
        $quote->setTotalsCollectedFlag(false)->collectTotals();
        return $quote;
    }

    private function verifyConfigurationLine(Item $item, bool $engraved, int $engravedQuantity): void
    {
        $this->verify(
            $item,
            $engraved ? self::FULL_PRICE : self::UNENGRAVED_PRICE,
            $engraved ? $engravedQuantity : 1,
            $engraved ? 9 : 8
        );
    }

    private function verify(Item $item, float $price, int $quantity, int $rowCount): void
    {
        $this->check(abs((float)$item->getPrice() - $price) < self::PRICE_TOLERANCE, 'Unexpected unit price: ' . $item->getPrice());
        $this->check((int)$item->getQty() === $quantity, 'Unexpected quantity.');
        $this->check(abs((float)$item->getRowTotal() - $price * $quantity) < self::PRICE_TOLERANCE, 'Unexpected row subtotal.');
        $this->check($item->getCustomPrice() === null, 'Custom price must not override the native price model.');
        $rows = $this->objectManager->get(OptionListBuilder::class)->build($item);
        $this->check(count($rows) === $rowCount && $rows[0]['value'] === '38', 'Incomplete configuration rows.');
        $totals = $this->objectManager->get(ItemConverter::class)->modelToDataObject($item);
        $totalRows = json_decode($totals->getOptions(), true, 512, JSON_THROW_ON_ERROR);
        $this->check(count($totalRows) === $rowCount, 'Totals lost options.');
        $this->check(abs((float)$totals->getPrice() - $price) < self::PRICE_TOLERANCE, 'Totals lost the unit price.');
        $this->check(
            abs((float)$totals->getRowTotal() - $price * $quantity) < self::PRICE_TOLERANCE,
            'Totals lost the row subtotal.'
        );
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }
}

$connection = $objectManager->get(ResourceConnection::class)->getConnection();
$connection->beginTransaction();
try {
    (new CartPriceConfigurationSmoke($objectManager))->run($identity === 'customer');
} finally {
    $connection->rollBack();
    $objectManager->get(CustomerSession::class)->setCustomerId(null);
    fwrite(STDOUT, 'Fixture transaction rolled back; no orders created.' . PHP_EOL);
}
