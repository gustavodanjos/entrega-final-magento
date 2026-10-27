<?php
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\State;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Model\ComponentFactory;
use Nuvem\AtelieSales\Api\ComponentStockInterface;

require dirname(__DIR__, 6) . '/app/bootstrap.php';

function assertAvailabilityScenario(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function requestAvailability(string $url, int $expectedStatus = 200): array
{
    $request = curl_init($url);
    curl_setopt_array($request, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($request);
    assertAvailabilityScenario(is_string($response), 'HTTP availability request failed.');
    assertAvailabilityScenario(
        curl_getinfo($request, CURLINFO_HTTP_CODE) === $expectedStatus,
        'Unexpected HTTP status.'
    );
    $headerSize = curl_getinfo($request, CURLINFO_HEADER_SIZE);
    $headers = strtolower(substr($response, 0, $headerSize));
    curl_close($request);
    assertAvailabilityScenario(str_contains($headers, 'no-store'), 'Missing no-store directive.');
    assertAvailabilityScenario(str_contains($headers, 'pragma: no-cache'), 'Missing Pragma header.');
    assertAvailabilityScenario(str_contains($headers, 'expires:'), 'Missing Expires header.');
    $body = json_decode(substr($response, $headerSize), true, 512, JSON_THROW_ON_ERROR);
    if ($expectedStatus === 200) {
        assertAvailabilityScenario(array_is_list($body), 'Availability is not a list.');
        foreach ($body as $component) {
            $keys = array_keys($component);
            sort($keys);
            assertAvailabilityScenario(
                $keys === ['componente', 'disponivel', 'ultimas_unidades']
                    && is_string($component['componente'])
                    && is_bool($component['disponivel'])
                    && is_bool($component['ultimas_unidades']),
                'Public response contains unexpected fields or types.'
            );
        }
    }
    return $body;
}

$testDatabase = getenv('F25_TEST_DATABASE');
if (is_string($testDatabase) && $testDatabase !== '') {
    require __DIR__ . '/IsolatedEnvironment.php';
    $objectManager = \Nuvem\AtelieSales\Test\Integration\createIsolatedEnvironment($testDatabase);
} else {
    $objectManager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
}
$database = $objectManager->get(DeploymentConfig::class)->get('db/connection/default/dbname');
assertAvailabilityScenario(
    is_string($database) && str_starts_with($database, 'f25_validation_'),
    'Run exclusively against an isolated database named f25_validation_*.'
);
$baseUrl = rtrim($argv[1] ?? '', '/');
assertAvailabilityScenario($baseUrl !== '', 'Provide the REST base URL of the same isolated database.');
$objectManager->get(State::class)->setAreaCode('frontend');
$repository = $objectManager->get(ComponentRepositoryInterface::class);
$stock = $objectManager->get(ComponentStockInterface::class);
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$product = $productRepository->get('NUVEM-RUNNER');
$template = $repository->getByCodigo('SOL-LUA-HOLO');
$component = $objectManager->get(ComponentFactory::class)->create();
$component->setData($template->getData());
$code = 'F25-' . bin2hex(random_bytes(6));
$component->setId(null)->setCodigo($code)->setEstoque(0)->setAtivo(true);
$repository->save($component);
$simple = $objectManager->get(ProductFactory::class)->create();
$simple->setSku($code)->setName('Availability validation')->setTypeId('simple')
    ->setAttributeSetId($product->getAttributeSetId())->setPrice(1)->setStatus(1)->setVisibility(1);

try {
    $productRepository->save($simple);
    $url = $baseUrl . '/atelie/disponibilidade/NUVEM-RUNNER';
    $initial = requestAvailability($url);
    $output = $objectManager->get(\Magento\Framework\Webapi\ServiceOutputProcessor::class);
    foreach ([
        \Nuvem\AtelieSales\Api\PublicAvailabilityInterface::class => 'getBySku',
        \Nuvem\AtelieSales\Api\AvailabilityManagementInterface::class => 'getAvailability',
    ] as $service => $method) {
        $serialized = $output->process($objectManager->get($service)->$method('NUVEM-RUNNER'), $service, $method);
        assertAvailabilityScenario($serialized === $initial, 'Availability services disagree with the HTTP contract.');
    }
    echo "PASS: both availability contracts serialize to the same public HTTP response\n";
    assertAvailabilityScenario(
        count(array_filter($initial, static fn (array $row): bool => $row['componente'] === $code)) === 1,
        'HTTP server must use the same isolated database and include the validation component.'
    );
    $stockScenarios = [
        6 => [true, false],
        5 => [true, true],
        1 => [true, true],
        0 => [false, false],
        2 => [true, true],
    ];
    foreach ($stockScenarios as $qty => $flags) {
        $stock->adjust($code, $qty, 'F2.5 isolated HTTP validation');
        $rows = requestAvailability($url);
        $matches = array_values(array_filter($rows, static fn (array $row): bool => $row['componente'] === $code));
        assertAvailabilityScenario(count($matches) === 1, 'Validation component is missing from HTTP response.');
        $row = $matches[0];
        assertAvailabilityScenario(
            [$row['disponivel'], $row['ultimas_unidades']] === $flags,
            'Next HTTP call did not reflect the committed stock adjustment.'
        );
    }
    requestAvailability($baseUrl . '/atelie/disponibilidade/F25-MISSING', 404);
    requestAvailability($baseUrl . '/atelie/disponibilidade/' . $code, 400);
    $component->setAtivo(false);
    $repository->save($component);
    $rows = requestAvailability($url);
    assertAvailabilityScenario(
        array_filter($rows, static fn (array $row): bool => $row['componente'] === $code) === [],
        'Inactive component remains in public availability.'
    );
    $criteria = $objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class)
        ->addFilter('ativo', 1)->create();
    $active = $repository->getList($criteria)->getItems();
    try {
        foreach ($active as $activeComponent) {
            $activeComponent->setAtivo(false);
            $repository->save($activeComponent);
        }
        assertAvailabilityScenario(requestAvailability($url) === [], 'Empty catalog must return an empty list.');
    } finally {
        foreach ($active as $activeComponent) {
            $activeComponent->setAtivo(true);
            $repository->save($activeComponent);
        }
    }
    echo "PASS: empty component catalog returns an HTTP empty list with no-cache headers\n";
    echo "PASS: REST fields, headers, 404/400, inactive components and stock changes without cache cleaning\n";
} finally {
    $repository->delete($component);
    if ($simple->getId()) {
        $productRepository->delete($simple);
    }
}
