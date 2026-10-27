<?php
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\DataObject;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Model\ConfigurationSerializer;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    return;
}
if (($argv[1] ?? '') !== '--run' || !in_array($argv[2] ?? '', ['guest', 'customer'], true)) {
    throw new InvalidArgumentException('Use --run guest|customer on a disposable local database.');
}
require dirname(__DIR__, 6) . '/app/bootstrap.php';
$objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$objectManager->get(State::class)->setAreaCode('frontend');
$objectManager->configure($objectManager->get(ConfigLoaderInterface::class)->load('frontend'));

class EditCartSmoke
{
    private const SKU = 'NUVEM-RUNNER';
    private const SELECTIONS = ['cabedal' => 'CAB-TRICO-LAVANDA'];

    public function __construct(private readonly ObjectManagerInterface $objectManager)
    {
    }

    public function run(bool $authenticated): void
    {
        $storeManager = $this->objectManager->get(StoreManagerInterface::class);
        $storeManager->setCurrentStore($storeManager->getDefaultStoreView());
        $management = $this->objectManager->get(CartManagementInterface::class);
        if ($authenticated) {
            $customer = $this->objectManager->get(CustomerInterfaceFactory::class)->create();
            $customer->setWebsiteId((int) $storeManager->getStore()->getWebsiteId())
                ->setEmail('f27-' . bin2hex(random_bytes(8)) . '@example.invalid')
                ->setFirstname('F27')->setLastname('Smoke');
            $customer = $this->objectManager->get(CustomerRepositoryInterface::class)->save($customer);
            $this->objectManager->get(CustomerSession::class)->setCustomerId($customer->getId());
            $id = (int) $management->createEmptyCartForCustomer((int) $customer->getId());
        } else {
            $id = (int) $management->createEmptyCart();
        }
        $quote = $this->reload($id);
        $configuration = [
            'tamanho' => 38,
            'gravacao' => null,
            'selecoes' => [],
        ];
        foreach ([
            'cabedal' => 'CAB-TRICO-LAVANDA', 'biqueira' => 'BIQ-LISA-CEU',
            'calcanhar' => 'CAL-ACOLCHOADO-CEU', 'cadarco' => 'CAD-ELASTICO-MENTA',
            'emblema' => 'EMB-NUVEM-PESSEGO', 'entressola' => 'ENT-EVA-NUVEM',
            'solado' => 'SOL-TRANSLUCIDO-GELO',
        ] as $group => $component) {
            $configuration['selecoes'][] = ['grupo' => $group, 'componente' => $component];
        }
        foreach (['LUIZ', null] as $engraving) {
            $configuration['gravacao'] = $engraving;
            $product = $this->objectManager->get(ProductRepositoryInterface::class)->get(self::SKU, false, null, true);
            $item = $quote->addProduct($product, new DataObject([
                'qty' => $engraving ? 3 : 1,
                'atelie' => $configuration,
            ]));
            $this->check(!is_string($item), is_string($item) ? $item : 'Cannot add fixture item.');
        }
        $quote->collectTotals();
        $this->objectManager->get(CartRepositoryInterface::class)->save($quote);
        $this->verifyEditing($id);
    }

    private function reload(int $cartId): \Magento\Quote\Model\Quote
    {
        return $this->objectManager->create(CartRepositoryInterface::class)->get($cartId);
    }

    private function check(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    private function verifyEditing(int $cartId): void
    {
        $request = $this->objectManager->get(\Magento\Framework\App\RequestInterface::class);
        $request->setRouteName('checkout')->setControllerName('cart')->setActionName('updateItemOptions');
        $quote = $this->reload($cartId);
        $items = $quote->getAllVisibleItems();
        $original = $items[0];
        $itemId = (int) $original->getId();
        $otherId = (int) $items[1]->getId();
        $serializer = $this->objectManager->get(ConfigurationSerializer::class);
        $configuration = json_decode($original->getOptionByCode('atelie_configuracao')->getValue(), true);
        $initial = $configuration;
        $configuration['gravacao'] = '';
        $updated = $quote->updateItem($itemId, new \Magento\Framework\DataObject([
            'atelie' => $configuration,
            'custom_price' => 1,
            'price' => 1,
            'qty' => 99,
        ]));
        $this->check($updated === $original, 'Editing must return the original item object.');
        $quote->collectTotals();
        $this->objectManager->get(CartRepositoryInterface::class)->save($quote);
        $quote = $this->reload($cartId);
        $this->check(count($quote->getAllVisibleItems()) === 2, 'Editing merged identical lines.');
        $this->check((int) $quote->getItemById($otherId)->getQty() === 1, 'Editing changed the other line.');
        $line = $quote->getItemById($itemId);
        $this->check((int) $line->getQty() === 3, 'Editing changed quantity.');
        $this->check($line->getCustomPrice() === null, 'Editing accepted a custom price.');
        $this->check($serializer->unserialize($line->getOptionByCode('atelie_configuracao')->getValue())
            ->getEngraving() === null, 'Editing failed to remove engraving.');
        $this->check($line->getBuyRequest()->getData('atelie')['gravacao'] === '', 'Buy request is stale.');

        $catalog = $this->objectManager->get(\Nuvem\Atelie\Api\AtelieCatalogInterface::class)->getBySku(self::SKU);
        $alternative = null;
        foreach ($catalog['grupos'] as $group) {
            if ($group['codigo'] === 'cabedal') {
                foreach ($group['componentes'] as $component) {
                    if ($component['codigo'] !== self::SELECTIONS['cabedal'] && $component['acabamento'] === 'trico') {
                        $alternative = $component['codigo'];
                        break;
                    }
                }
            }
        }
        $this->check($alternative !== null, 'Fixture requires an alternative upper.');
        $configuration['tamanho'] = 39;
        foreach ($configuration['selecoes'] as &$selection) {
            if ($selection['grupo'] === 'cabedal') {
                $selection['componente'] = $alternative;
            }
        }
        unset($selection);
        $oldPreview = $this->objectManager->get(\Nuvem\AtelieStudio\Api\PreviewInterface::class)
            ->getUrl($line->getOptionByCode('atelie_configuracao')->getValue());
        $quote->updateItem($itemId, new \Magento\Framework\DataObject(['atelie' => $configuration]));
        $quote->collectTotals();
        $this->objectManager->get(CartRepositoryInterface::class)->save($quote);
        $quote = $this->reload($cartId);
        $line = $quote->getItemById($itemId);
        $serialized = $line->getOptionByCode('atelie_configuracao')->getValue();
        $this->check($serializer->unserialize($serialized)->getSize() === 39, 'Editing did not persist the size.');
        $expected = $this->objectManager->get(\Nuvem\Atelie\Api\PriceCalculatorInterface::class)
            ->calculate(self::SKU, $serializer->unserialize($serialized), (int) $quote->getStoreId());
        $this->check(abs((float) $line->getPrice() - $expected) < 0.001, 'Upper price was not recalculated.');
        $this->check($oldPreview !== $this->objectManager->get(\Nuvem\AtelieStudio\Api\PreviewInterface::class)
            ->getUrl($serialized), 'Editing did not change the preview URL.');
        $invalid = $configuration;
        $invalid['tamanho'] = 999;
        try {
            $quote->updateItem($itemId, new \Magento\Framework\DataObject(['atelie' => $invalid]));
            throw new RuntimeException('Invalid configuration was accepted.');
        } catch (\Magento\Framework\Exception\LocalizedException $exception) {
            $this->check($line->getOptionByCode('atelie_configuracao')->getValue() === $serialized,
                'Invalid configuration changed the original line.');
        }
        $quote->updateItem($itemId, new \Magento\Framework\DataObject(['atelie' => $initial]));
        $quote->collectTotals();
        $this->objectManager->get(CartRepositoryInterface::class)->save($quote);
        fwrite(STDOUT, 'Edit: item_id, quantity, identical lines, upper, size, engraving, price, preview and rejection OK.'
            . PHP_EOL);
    }
}

$connection = $objectManager->get(ResourceConnection::class)->getConnection();
$connection->beginTransaction();
try {
    (new EditCartSmoke($objectManager))->run($argv[2] === 'customer');
} finally {
    $connection->rollBack();
    $objectManager->get(CustomerSession::class)->setCustomerId(null);
    fwrite(STDOUT, 'Fixture transaction rolled back; no orders created.' . PHP_EOL);
}
