<?php
declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Declaration\Schema\Declaration\TableElement\ElementNameResolver;
use Magento\Framework\Setup\Declaration\Schema\SchemaConfig;

require dirname(__DIR__, 6) . '/app/bootstrap.php';

$manager = Bootstrap::create(BP, $_SERVER)->getObjectManager();
$resource = $manager->get(ResourceConnection::class);
$connection = $resource->getConnection();
if (!str_starts_with((string) $connection->fetchOne('SELECT DATABASE()'), 'f23_validation_')) {
    throw new RuntimeException('Run exclusively against an isolated database named f23_validation_*.');
}

$tableName = 'nuvem_atelie_movimentacao';
$columns = ['quote_id', 'componente_id'];
$table = $manager->get(SchemaConfig::class)->getDeclarationConfig()->getTableByName($tableName);
$indexName = $manager->get(ElementNameResolver::class)->getFullIndexName($table, $columns, 'btree');
$moduleDirectory = dirname(__DIR__, 2);
$schema = simplexml_load_file($moduleDirectory . '/etc/db_schema.xml');
$declaration = $schema->xpath("//table[@name='$tableName']/index[@referenceId='$indexName']");
$declaredColumns = [];
foreach ($declaration[0]->column ?? [] as $column) {
    $declaredColumns[] = (string) $column['name'];
}
if (count($declaration) !== 1 || $declaredColumns !== $columns) {
    throw new RuntimeException('The compound index declaration must use its generated name and column order.');
}
$whitelist = json_decode(
    file_get_contents($moduleDirectory . '/etc/db_schema_whitelist.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$whitelistedIndexes = $whitelist[$tableName]['index'];
if (($whitelistedIndexes[$indexName] ?? false) !== true
    || isset($whitelistedIndexes['NUVEM_ATELIE_MOV_QUOTE_COMPONENT'])) {
    throw new RuntimeException('Whitelist does not match the generated compound index name.');
}

$indexes = [];
$physicalTable = $connection->quoteIdentifier($resource->getTableName($tableName));
foreach ($connection->fetchAll('SHOW INDEX FROM ' . $physicalTable) as $row) {
    $indexes[$row['Key_name']][] = $row['Column_name'];
}
$matchingIndexes = array_filter($indexes, static fn (array $indexedColumns): bool => $indexedColumns === $columns);
if (array_keys($matchingIndexes) !== [$indexName]) {
    throw new RuntimeException('Expected exactly one compound index with its generated physical name.');
}
foreach (['index' => $table->getIndexes(), 'constraint' => $table->getConstraints()] as $kind => $elements) {
    foreach ($elements as $element) {
        $name = $element->getName();
        if (($whitelist[$tableName][$kind][$name] ?? false) !== true) {
            throw new RuntimeException('Generated schema element missing from whitelist: ' . $name);
        }
    }
}
$quoteColumns = $connection->describeTable($resource->getTableName('quote'));
foreach (['atelie_stock_attempt_id' => 32, 'atelie_stock_attempt_state' => 24] as $name => $length) {
    if (!isset($quoteColumns[$name]) || !$quoteColumns[$name]['NULLABLE']
        || (int) $quoteColumns[$name]['LENGTH'] !== $length
        || ($whitelist['quote']['column'][$name] ?? false) !== true) {
        throw new RuntimeException('Quote ownership declaration does not match physical schema: ' . $name);
    }
}
$ledger = $connection->fetchRow('SELECT COUNT(*) AS rows_count, COALESCE(SUM(quantidade), 0) AS net,'
    . ' COALESCE(SUM(movimentacao_id), 0) AS ids_sum FROM ' . $physicalTable);
$ledger['digest'] = hash('sha256', json_encode($connection->fetchAll(
    'SELECT * FROM ' . $physicalTable . ' ORDER BY movimentacao_id'
), JSON_THROW_ON_ERROR));
$report = ['index' => $indexName, 'columns' => $columns, 'ledger' => $ledger];
fwrite(STDOUT, json_encode($report, JSON_THROW_ON_ERROR) . "\n");
fwrite(STDOUT, "PASS: declaration, generated whitelist and physical compound index agree\n");
