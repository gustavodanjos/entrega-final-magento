<?php
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product\ConfigurationPool;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Quote\Model\Cart\Totals\ItemConverter;
use Magento\Quote\Model\Quote\Item;
use Magento\Quote\Model\Quote\Item\Option;
use Nuvem\AtelieSales\Helper\Catalog\Product\Configuration;

// Exercises the installed DI and real catalog without saving a quote or product.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 6) . '/app/bootstrap.php';

$area = $argv[1] ?? 'frontend';
if (!in_array($area, ['frontend', 'webapi_rest'], true)) {
    throw new InvalidArgumentException('Use frontend or webapi_rest.');
}
$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(State::class)->setAreaCode($area);
$objectManager->configure($objectManager->get(ConfigLoaderInterface::class)->load($area));
$helper = $objectManager->get(ConfigurationPool::class)->getByProductType('atelie');
if (!$helper instanceof Configuration) {
    throw new RuntimeException($area . ': ConfigurationPool did not resolve the atelie helper.');
}

$codes = [
    'cabedal' => 'CAB-TRICO-LAVANDA',
    'biqueira' => 'BIQ-LISA-CEU',
    'calcanhar' => 'CAL-ACOLCHOADO-CEU',
    'cadarco' => 'CAD-ELASTICO-MENTA',
    'emblema' => 'EMB-NUVEM-PESSEGO',
    'entressola' => 'ENT-EVA-NUVEM',
    'solado' => 'SOL-TRANSLUCIDO-GELO',
];
$selections = [];
foreach ($codes as $group => $component) {
    $selections[] = ['grupo' => $group, 'componente' => $component];
}
$serialized = json_encode([
    'tamanho' => 38,
    'gravacao' => '<b>LUIZ</b>',
    'selecoes' => $selections,
], JSON_THROW_ON_ERROR);
$product = $objectManager->get(ProductRepositoryInterface::class)->get('NUVEM-RUNNER');
$item = $objectManager->create(Item::class);
$item->setProduct($product)->setProductType('atelie');
$item->addOption(
    $objectManager->create(Option::class)->setCode('atelie_configuracao')->setValue($serialized)
);
$totals = $objectManager->get(ItemConverter::class)->modelToDataObject($item);
$rows = json_decode($totals->getOptions(), true, 512, JSON_THROW_ON_ERROR);
if (count($rows) !== 9 || $rows[0]['value'] !== '38' || $rows[1]['value'] !== '&lt;b&gt;LUIZ&lt;/b&gt;') {
    throw new RuntimeException($area . ': totals lost configuration rows or escaped engraving.');
}

echo $area . ': OK — 7 componentes, tamanho 38 e gravação escapada; nenhum carrinho salvo.' . PHP_EOL;
