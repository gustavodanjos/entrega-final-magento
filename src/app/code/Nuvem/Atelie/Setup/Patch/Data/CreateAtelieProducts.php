<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\Atelie\Setup\Patch\Data;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use RuntimeException;
use Throwable;

/**
 * Creates the two atelie products (NUVEM-RUNNER and NUVEM-MINI) from fixture data.
 */
class CreateAtelieProducts implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param EavSetupFactory $eavSetupFactory
     * @param ProductFactory $productFactory
     * @param ProductRepositoryInterface $productRepository
     * @param StoreManagerInterface $storeManager
     * @param State $appState
     * @param StockRegistryInterface $stockRegistry
     * @param ModuleReader $moduleReader
     * @param FileDriver $fileDriver
     * @param Csv $csvReader
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory,
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ModuleReader $moduleReader,
        private readonly FileDriver $fileDriver,
        private readonly Csv $csvReader
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->appState->emulateAreaCode(
            Area::AREA_ADMINHTML,
            [$this, 'executeProductCreation']
        );

        return $this;
    }

    /**
     * Execute product creation logic within emulated admin area.
     *
     * @return void
     */
    public function executeProductCreation(): void
    {
        $setup = $this->moduleDataSetup;
        $setup->startSetup();

        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);
        $attributeSetId = (int) $eavSetup->getAttributeSet(Product::ENTITY, 'Ateliê', 'attribute_set_id');
        if (!$attributeSetId) {
            $attributeSetId = (int) $eavSetup->getDefaultAttributeSetId(Product::ENTITY);
        }

        $websiteIds = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteIds[] = (int) $website->getId();
        }
        if (empty($websiteIds)) {
            $websiteIds = [1];
        }

        $defaultStoreId = (int) $this->storeManager->getDefaultStoreView()->getId();
        $rows = $this->readCsv();

        foreach ($rows as $row) {
            $sku = $row['sku'];
            $name = $row['nome'];
            $price = (float) $row['preco_base'];
            $urlKey = strtolower(str_replace('_', '-', (string) preg_replace('/[^a-zA-Z0-9]+/', '-', $sku)));

            try {
                $product = $this->productRepository->get($sku, true, Store::DEFAULT_STORE_ID, true);
            } catch (NoSuchEntityException) {
                $product = $this->productFactory->create();
            }

            $product->setStoreId(Store::DEFAULT_STORE_ID);
            $product->setTypeId(Atelie::TYPE_CODE);
            $product->setAttributeSetId($attributeSetId);
            $product->setSku($sku);
            $product->setName($name);
            $product->setPrice($price);
            $product->setData('url_key', $urlKey);
            $product->setStatus(Status::STATUS_ENABLED);
            $product->setVisibility(Visibility::VISIBILITY_BOTH);
            $product->setWebsiteIds($websiteIds);
            $product->setData('tax_class_id', 2);
            $product->setData('atelie_tamanho_min', (int) $row['tamanho_min']);
            $product->setData('atelie_tamanho_max', (int) $row['tamanho_max']);
            $product->setData('atelie_permite_gravacao', (int) $row['permite_gravacao']);
            $product->setData('atelie_prazo_producao', (int) $row['prazo_producao_dias']);
            $product->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 0,
                'is_in_stock' => 1,
                'qty' => 0,
            ]);

            $categoryIds = $product->getCategoryIds() ?: [];
            if ($this->categoryExists(38) && !in_array(38, $categoryIds, true)) {
                $categoryIds[] = 38;
            }
            if (!empty($categoryIds)) {
                $product->setCategoryIds($categoryIds);
            }

            $savedProduct = $this->productRepository->save($product);
            $productId = (int) $savedProduct->getId();

            $setup->getConnection()->update(
                $setup->getTable('catalog_product_entity'),
                ['attribute_set_id' => $attributeSetId],
                ['entity_id = ?' => $productId]
            );

            try {
                $stockItem = $this->stockRegistry->getStockItemBySku($sku);
                $stockItem->setIsInStock(true);
                $stockItem->setManageStock(false);
                $stockItem->setUseConfigManageStock(false);
                $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
            } catch (Throwable $e) {
                // Stock item status is alternatively persisted via product stockData
                unset($e);
            }

            $requestPath = $urlKey . '.html';
            $this->ensureUrlRewrite($productId, $requestPath, $defaultStoreId);
            if ($defaultStoreId !== 1) {
                $this->ensureUrlRewrite($productId, $requestPath, 1);
            }
        }

        $setup->endSetup();
    }

    /**
     * Ensure URL rewrite exists for the product.
     *
     * @param int $productId
     * @param string $requestPath
     * @param int $storeId
     * @return void
     */
    private function ensureUrlRewrite(int $productId, string $requestPath, int $storeId): void
    {
        $connection = $this->moduleDataSetup->getConnection();
        $table = $this->moduleDataSetup->getTable('url_rewrite');
        $targetPath = 'catalog/product/view/id/' . $productId;

        $select = $connection->select()
            ->from($table, 'url_rewrite_id')
            ->where('request_path = ?', $requestPath)
            ->where('store_id = ?', $storeId);

        $existingId = $connection->fetchOne($select);
        if (!$existingId) {
            $connection->insert($table, [
                'entity_type' => 'product',
                'entity_id' => $productId,
                'request_path' => $requestPath,
                'target_path' => $targetPath,
                'redirect_type' => 0,
                'store_id' => $storeId,
                'is_autogenerated' => 1,
            ]);
        }
    }

    /**
     * Check if category exists by ID.
     *
     * @param int $categoryId
     * @return bool
     */
    private function categoryExists(int $categoryId): bool
    {
        $connection = $this->moduleDataSetup->getConnection();
        $select = $connection->select()
            ->from($this->moduleDataSetup->getTable('catalog_category_entity'), 'entity_id')
            ->where('entity_id = ?', $categoryId);

        return (bool) $connection->fetchOne($select);
    }

    /**
     * Read product fixture CSV file.
     *
     * @return array<int, array<string, string>>
     */
    private function readCsv(): array
    {
        $modulePath = $this->moduleReader->getModuleDir('', 'Nuvem_Atelie');
        $path = $modulePath . '/Setup/fixtures/produtos.csv';
        if (!$this->fileDriver->isExists($path)) {
            $path = BP . '/kit/dados/produtos.csv';
        }

        if (!$this->fileDriver->isExists($path)) {
            throw new RuntimeException(sprintf('Product CSV fixture not found at %s', $path));
        }

        $data = $this->csvReader->getData($path);
        if (empty($data)) {
            throw new RuntimeException(sprintf('CSV file %s is empty', $path));
        }

        $headers = array_shift($data);
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $headers = array_map(static fn ($header): string => trim((string) $header), $headers);

        $rows = [];
        foreach ($data as $values) {
            if ($values === [null] || empty($values)) {
                continue;
            }

            if (count($values) !== count($headers)) {
                throw new RuntimeException(sprintf('Invalid column count in CSV file %s', $path));
            }

            $row = array_combine($headers, $values);
            if ($row !== false) {
                $rows[] = array_map(static fn ($value): string => trim((string) $value), $row);
            }
        }

        return $rows;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [
            CreateAtelieProductAttributes::class,
            EnableProductAttributesForAtelie::class,
        ];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
