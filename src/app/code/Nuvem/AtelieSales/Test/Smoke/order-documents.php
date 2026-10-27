<?php
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\LayoutFactory;
use Magento\Quote\Model\Quote\Item as QuoteItem;
use Magento\Quote\Model\Quote\Item\ToOrderItem;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\AtelieSales\Model\Order\ItemOptionsReader;

// Transactional fixtures exercise real sales persistence without leaving orders or changing stock.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 6) . '/app/bootstrap.php';
$area = $argv[1] ?? 'frontend';
if (!in_array($area, ['frontend', 'adminhtml'], true)) {
    throw new InvalidArgumentException('Use frontend or adminhtml, optionally followed by --mail.');
}
$sendMail = in_array('--mail', $argv, true);
$objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$objectManager->get(State::class)->setAreaCode($area);
$objectManager->configure($objectManager->get(ConfigLoaderInterface::class)->load($area));
$store = $objectManager->get(StoreManagerInterface::class)->getStore('default');
$objectManager->get(StoreManagerInterface::class)->setCurrentStore($store);
$objectManager->get(Magento\Framework\View\DesignInterface::class)->setDesignTheme(
    $area === 'frontend' ? 'Nuvem/pastel' : 'Magento/backend',
    $area
);
$reader = $objectManager->get(ItemOptionsReader::class);
$connection = $objectManager->get(ResourceConnection::class)->getConnection();
$directory = BP . '/var/f26-evidence';
if (!is_dir($directory)) {
    mkdir($directory, 0775, true);
}
$connection->beginTransaction();
try {
    $order = $objectManager->create(Order::class);
    $order->addData([
        'store_id' => $store->getId(), 'increment_id' => 'F26-' . date('mdHis') . random_int(10, 99),
        'state' => 'processing', 'status' => 'processing', 'customer_is_guest' => 1,
        'customer_email' => 'f26-smoke@example.invalid', 'customer_firstname' => 'Cliente',
        'customer_lastname' => 'Teste F2.6', 'base_currency_code' => 'BRL',
        'global_currency_code' => 'BRL', 'order_currency_code' => 'BRL', 'store_currency_code' => 'BRL',
        'base_to_global_rate' => 1, 'base_to_order_rate' => 1, 'store_to_order_rate' => 1,
        'subtotal' => 1800, 'base_subtotal' => 1800, 'grand_total' => 1800, 'base_grand_total' => 1800,
        'total_qty_ordered' => 4, 'total_item_count' => 3, 'created_at' => gmdate('Y-m-d H:i:s'),
        'shipping_method' => 'flatrate_flatrate', 'shipping_description' => 'Frete de teste local',
    ]);
    foreach (['billing', 'shipping'] as $type) {
        $address = $objectManager->create(Address::class);
        $address->addData([
            'address_type' => $type, 'firstname' => 'Cliente', 'lastname' => 'Teste F2.6',
            'street' => 'Rua de teste, 26', 'city' => 'Franca', 'region' => 'São Paulo',
            'country_id' => 'BR', 'postcode' => '14400-000', 'telephone' => '1100000000',
        ]);
        $order->addAddress($address);
    }
    $order->setPayment($objectManager->create(Payment::class)->setMethod('checkmo'));
    $codes = [
        'cabedal' => 'CAB-TRICO-LAVANDA', 'biqueira' => 'BIQ-LISA-CEU',
        'calcanhar' => 'CAL-ACOLCHOADO-CEU', 'cadarco' => 'CAD-ELASTICO-MENTA',
        'emblema' => 'EMB-NUVEM-PESSEGO', 'entressola' => 'ENT-EVA-NUVEM',
        'solado' => 'SOL-TRANSLUCIDO-GELO',
    ];
    $selections = [];
    foreach ($codes as $group => $component) {
        $selections[] = ['grupo' => $group, 'componente' => $component];
    }
    foreach ([['38', '<b>LUIZ</b>', 2], ['39', '', 1]] as [$size, $engraving, $quantity]) {
        $product = clone $objectManager->get(ProductRepositoryInterface::class)->get('NUVEM-RUNNER');
        $serialized = json_encode([
            'tamanho' => (int) $size, 'gravacao' => $engraving, 'selecoes' => $selections,
        ], JSON_THROW_ON_ERROR);
        $quote = $objectManager->create(Magento\Quote\Model\Quote::class)->setStore($store);
        $quoteItem = $objectManager->create(QuoteItem::class)->setQuote($quote)->setProduct($product);
        $quoteItem->addOption($objectManager->create(Magento\Quote\Model\Quote\Item\Option::class)
            ->setCode('atelie_configuracao')->setValue($serialized));
        $quoteItem->addData(['qty' => $quantity, 'price' => 500, 'base_price' => 500]);
        $item = $objectManager->get(ToOrderItem::class)->convert($quoteItem);
        $item->addData([
            'name' => 'Nuvem Runner', 'sku' => 'NUVEM-RUNNER', 'product_type' => 'atelie',
            'qty_ordered' => $quantity, 'price' => 500, 'base_price' => 500,
            'row_total' => 500 * $quantity, 'base_row_total' => 500 * $quantity,
        ]);
        $order->addItem($item);
    }
    $simple = $objectManager->create(Item::class)->addData([
        'name' => 'Produto comum de teste', 'sku' => 'F26-SIMPLE', 'product_type' => 'simple',
        'qty_ordered' => 1, 'price' => 300, 'base_price' => 300, 'row_total' => 300, 'base_row_total' => 300,
        'product_options' => ['additional_options' => [['label' => 'Opção comum', 'value' => 'Original']]],
    ]);
    $order->addItem($simple);
    $objectManager->get(Magento\Sales\Model\ResourceModel\Order::class)->save($order);
    $order = $objectManager->create(Order::class)->load($order->getId());
    $items = array_values($order->getAllItems());
    if (count($reader->read($items[0])) !== 9 || count($reader->read($items[1])) !== 8) {
        throw new RuntimeException('Quote conversion or persisted snapshot lost rows.');
    }
    $historicalRows = $reader->read($items[0]);
    $componentRepository = $objectManager->get(Nuvem\Atelie\Api\ComponentRepositoryInterface::class);
    $component = $componentRepository->getByCodigo('CAB-TRICO-LAVANDA');
    $component->setNome('Changed after purchase')->setCorHex('#000000');
    $componentRepository->save($component);
    if ($reader->read($items[0]) !== $historicalRows) {
        throw new RuntimeException('Catalog changes rewrote the order snapshot.');
    }
    echo 'History: persisted names and colors survive a catalog edit' . PHP_EOL;
    $invoice = $objectManager->create(Invoice::class)->setOrder($order)->addData([
        'store_id' => $store->getId(), 'increment_id' => $order->getIncrementId() . '-I',
        'state' => 2, 'total_qty' => 3, 'subtotal' => 1300, 'base_subtotal' => 1300,
        'grand_total' => 1300, 'base_grand_total' => 1300, 'created_at' => gmdate('Y-m-d H:i:s'),
    ]);
    $creditmemo = $objectManager->create(Creditmemo::class)->setOrder($order)->addData([
        'store_id' => $store->getId(), 'increment_id' => $order->getIncrementId() . '-C',
        'state' => 2, 'total_qty' => 3, 'subtotal' => 1300, 'base_subtotal' => 1300,
        'grand_total' => 1300, 'base_grand_total' => 1300, 'created_at' => gmdate('Y-m-d H:i:s'),
    ]);
    foreach ($items as $item) {
        foreach ([[$invoice, Invoice\Item::class], [$creditmemo, Creditmemo\Item::class]] as [$document, $class]) {
            $document->addItem($objectManager->create($class)->setOrderItem($item)->setOrder($order)->addData([
                'name' => $item->getName(), 'sku' => $item->getSku(), 'qty' => 1,
                'price' => $item->getPrice(), 'base_price' => $item->getPrice(),
                'row_total' => $item->getPrice(), 'base_row_total' => $item->getPrice(),
            ]));
        }
    }
    $objectManager->get(Magento\Sales\Model\ResourceModel\Order\Invoice::class)->save($invoice);
    $objectManager->get(Magento\Sales\Model\ResourceModel\Order\Creditmemo::class)->save($creditmemo);
    $registry = $objectManager->get(Registry::class);
    $registry->register('current_order', $order);
    $registry->register('current_invoice', $invoice);
    $registry->register('current_creditmemo', $creditmemo);
    $documents = ['order' => $order, 'invoice' => $invoice, 'creditmemo' => $creditmemo];
    foreach ($documents as $type => $document) {
        $layout = $objectManager->get(LayoutFactory::class)->create();
        if ($area === 'frontend') {
            $handle = ['order' => 'sales_order_view', 'invoice' => 'sales_order_invoice',
                'creditmemo' => 'sales_order_creditmemo'][$type];
        }
        if ($area === 'frontend') {
            $layout->getUpdate()->load([$handle]);
            $layout->getUpdate()->addUpdate('<container name="content"/>');
            $layout->generateXml();
            $layout->generateElements();
        }
        if ($area === 'frontend') {
            $list = ['order' => 'sales.order.items.renderers', 'invoice' => 'sales.order.invoice.renderers',
                'creditmemo' => 'sales.order.creditmemo.renderers'][$type];
            $renderer = $layout->getBlock($list)->getRenderer('atelie');
            $documentItem = $type === 'order' ? $items[0] : $document->getAllItems()[0];
            $renderer->setItem($documentItem);
            if (count($renderer->getItemOptions()) !== 9) {
                throw new RuntimeException($type . ': account renderer duplicated or lost rows.');
            }
            $html = $renderer->toHtml();
        } else {
            $column = $layout->createBlock(Magento\Sales\Block\Adminhtml\Items\Column\Name::class)
                ->setTemplate('Magento_Sales::items/column/name.phtml');
            $documentItem = $type === 'order' ? $items[0] : $document->getAllItems()[0];
            $column->setItem($documentItem);
            if (count($column->getOrderOptions()) !== 9) {
                throw new RuntimeException($type . ': admin column duplicated or lost rows.');
            }
            $html = $column->toHtml();
        }
        if (!str_contains($html, '&lt;b&gt;LUIZ&lt;/b&gt;') || str_contains($html, '<b>LUIZ</b>')) {
            throw new RuntimeException($area . ' ' . $type . ': native formatting changed the literal engraving.');
        }
        file_put_contents($directory . '/' . $area . '-' . $type . '.html', $html);
        echo $area . ' ' . $type . ': 9 historical rows, native renderer OK' . PHP_EOL;
    }
    if ($area === 'frontend') {
        $pdf = $objectManager->create(Magento\Sales\Model\Order\Pdf\Invoice::class)->getPdf([$invoice]);
        file_put_contents($directory . '/invoice.pdf', $pdf->render());
        echo 'PDF: ' . count($pdf->pages) . ' pages generated with native invoice renderer' . PHP_EOL;
        $pdfOrderItem = $invoice->getAllItems()[0]->getOrderItem();
        $originalOptions = $pdfOrderItem->getProductOptions();
        $longOptions = $originalOptions;
        foreach ([ItemOptionsReader::SNAPSHOT_KEY, 'additional_options', 'attributes_info'] as $key) {
            $longOptions[$key][2]['value'] = str_repeat('Nome longo de componente ', 200);
        }
        $pdfOrderItem->setProductOptions($longOptions);
        try {
            $longPdf = $objectManager->create(Magento\Sales\Model\Order\Pdf\Invoice::class)->getPdf([$invoice]);
            file_put_contents($directory . '/invoice-long.pdf', $longPdf->render());
            if (count($longPdf->pages) < 3) {
                throw new RuntimeException('Long names did not exercise multiple PDF page boundaries.');
            }
            echo 'PDF long names: ' . count($longPdf->pages) . ' pages generated' . PHP_EOL;
        } finally {
            $pdfOrderItem->setProductOptions($originalOptions);
        }
        foreach (['order' => 'sales_email_order_items', 'invoice' => 'sales_email_order_invoice_items',
            'creditmemo' => 'sales_email_order_creditmemo_items'] as $type => $handle) {
            $layout = $objectManager->get(LayoutFactory::class)->create();
            $layout->getUpdate()->load([$handle]);
            $layout->generateXml();
            $layout->generateElements();
            $list = 'sales.email.order' . ($type === 'order' ? '' : '.' . $type) . '.renderers';
            $renderer = $layout->getBlock($list)->getRenderer('atelie');
            $documentItem = $type === 'order' ? $items[0] : $documents[$type]->getAllItems()[0];
            $html = $renderer->setItem($documentItem)->toHtml();
            $dom = new DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new DOMXPath($dom);
            if ($xpath->query('//table[contains(@class,"atelie-ficha")]/tr')->length !== 9
                || $xpath->query('//td[contains(@class,"atelie-color-dot")]')->length !== 7
                || $xpath->query('//b')->length !== 0
                || !str_contains($html, '&lt;b&gt;LUIZ&lt;/b&gt;')
            ) {
                throw new RuntimeException($type . ': email table, swatches or escaping failed.');
            }
            file_put_contents($directory . '/email-' . $type . '-item.html', $html);
            echo 'email ' . $type . ': 9 rows, 7 colors, escaped engraving OK' . PHP_EOL;
        }
        if ($sendMail) {
            $senders = [
                'order' => Magento\Sales\Model\Order\Email\Sender\OrderSender::class,
                'invoice' => Magento\Sales\Model\Order\Email\Sender\InvoiceSender::class,
                'creditmemo' => Magento\Sales\Model\Order\Email\Sender\CreditmemoSender::class,
            ];
            foreach ($senders as $type => $sender) {
                if (!$objectManager->get($sender)->send($documents[$type], true)) {
                    throw new RuntimeException($type . ': native sender did not send email.');
                }
                echo 'Sent local test email: ' . $type . PHP_EOL;
            }
        }
    }
} finally {
    $connection->rollBack();
}
echo 'Fixtures rolled back; no stock movements or sales records retained.' . PHP_EOL;
