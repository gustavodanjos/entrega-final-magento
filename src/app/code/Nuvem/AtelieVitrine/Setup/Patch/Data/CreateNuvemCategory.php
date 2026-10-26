<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkRepositoryInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryProductLinkInterfaceFactory;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Nuvem\Atelie\Model\Product\Type\Atelie;
use Nuvem\Atelie\Setup\Patch\Data\CreateAtelieProducts;

class CreateNuvemCategory implements DataPatchInterface
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CategoryFactory $categoryFactory
     * @param CategoryRepositoryInterface $categoryRepository
     * @param ProductCollectionFactory $productCollectionFactory
     * @param CategoryProductLinkInterfaceFactory $productLinkFactory
     * @param CategoryLinkRepositoryInterface $categoryLinkRepository
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param BlockRepositoryInterface $blockRepository
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly CategoryProductLinkInterfaceFactory $productLinkFactory,
        private readonly CategoryLinkRepositoryInterface $categoryLinkRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $store = $this->storeManager->getDefaultStoreView();
        $categoryId = $this->getOrCreateCategory((int) $store->getRootCategoryId());
        $this->assignAtelieProducts($categoryId);
        $this->updateModelsLink($categoryId);

        return $this;
    }

    /**
     * Reuse an existing Nuvem category under the storefront root without overwriting admin edits.
     *
     * @param int $rootCategoryId
     * @return int
     */
    private function getOrCreateCategory(int $rootCategoryId): int
    {
        $category = $this->categoryCollectionFactory->create()
            ->setStoreId(Store::DEFAULT_STORE_ID)
            ->addAttributeToFilter('parent_id', $rootCategoryId)
            ->addAttributeToFilter('name', 'Nuvem')
            ->getFirstItem();
        if ($category->getId()) {
            return (int) $category->getId();
        }

        $category = $this->categoryFactory->create();
        $category->setParentId($rootCategoryId);
        $category->setName('Nuvem');
        $category->setIsActive(true);
        $category->setIncludeInMenu(true);
        $category->setCustomAttribute('url_key', 'nuvem');
        $category->setCustomAttribute('display_mode', Category::DM_PRODUCT);
        $category->setCustomAttribute('is_anchor', true);

        return (int) $this->categoryRepository->save($category)->getId();
    }

    /**
     * Add existing atelier models while preserving their other category assignments and positions.
     *
     * @param int $categoryId
     * @return void
     */
    private function assignAtelieProducts(int $categoryId): void
    {
        $products = $this->productCollectionFactory->create()
            ->addFieldToFilter('type_id', Atelie::TYPE_CODE);
        foreach ($products as $product) {
            if (in_array($categoryId, array_map('intval', $product->getCategoryIds()), true)) {
                continue;
            }

            $link = $this->productLinkFactory->create();
            $link->setCategoryId($categoryId);
            $link->setSku((string) $product->getSku());
            $link->setPosition(0);
            $this->categoryLinkRepository->save($link);
        }
    }

    /**
     * Replace only the provisional search destination, preserving customized navigation.
     *
     * @param int $categoryId
     * @return void
     */
    private function updateModelsLink(int $categoryId): void
    {
        try {
            $block = $this->getBlockByIdentifier->execute('nuvem-menu', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }

        $content = (string) $block->getContent();
        $updatedContent = str_replace(
            "{{store direct_url='catalogsearch/result/'}}?q=nuvem",
            "{{store direct_url='catalog/category/view/id/" . $categoryId . "/'}}",
            $content
        );
        if ($content !== $updatedContent) {
            $block->setContent($updatedContent);
            $this->blockRepository->save($block);
        }
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [CreateAtelieProducts::class, UnlinkPendingGallery::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
