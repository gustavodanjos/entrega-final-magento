<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

class UpdateHomePresentation implements DataPatchInterface
{
    private const HOME_LABEL = 'Início';
    private const LEGACY_SHOWCASE_IDENTIFIER = 'nuvem-criacoes-home';
    private const LEGACY_SHOWCASE_DIRECTIVE = '{{block class="Magento\\Cms\\Block\\BlockByIdentifier"'
        . ' identifier="nuvem-criacoes-home"}}';
    private const HOME_MENU_ITEM = '<li class="level0 level-top" data-role="nuvem-home-link">'
        . '<a class="level-top" href="{{store direct_url=\'\'}}"><span>Início</span></a></li>';

    /**
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     * @param PageRepositoryInterface $pageRepository
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param BlockRepositoryInterface $blockRepository
     */
    public function __construct(
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->updateHomePage();
        $this->deactivateLegacyShowcase();
        $this->addHomeNavigationLink();
        return $this;
    }

    /**
     * Keep the Page Builder content and the existing browser title during the rename.
     *
     * @return void
     */
    private function updateHomePage(): void
    {
        $page = $this->getPageByIdentifier->execute('home', Store::DEFAULT_STORE_ID);
        $content = (string) $page->getContent();
        $updatedContent = str_replace(self::LEGACY_SHOWCASE_DIRECTIVE, '', $content);
        $changed = false;

        if ($content !== $updatedContent) {
            $page->setContent($updatedContent);
            $changed = true;
        }
        if ($page->getTitle() !== self::HOME_LABEL) {
            // Magento uses the CMS title for the browser tab when meta_title is empty.
            if (!$page->getMetaTitle()) {
                $page->setMetaTitle($page->getTitle());
            }
            $page->setTitle(self::HOME_LABEL);
            $changed = true;
        }
        if ($changed) {
            $this->pageRepository->save($page);
        }
    }

    /**
     * Retire the editorial examples now supplied by the Page Builder widget.
     *
     * @return void
     */
    private function deactivateLegacyShowcase(): void
    {
        try {
            $block = $this->getBlockByIdentifier->execute(self::LEGACY_SHOWCASE_IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }
        if ($block->isActive()) {
            $block->setIsActive(false);
            $this->blockRepository->save($block);
        }
    }

    /**
     * Add a shared desktop/mobile link without replacing the existing navigation.
     *
     * @return void
     */
    private function addHomeNavigationLink(): void
    {
        try {
            $block = $this->getBlockByIdentifier->execute('nuvem-menu', Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            return;
        }
        $content = (string) $block->getContent();
        if (str_contains($content, 'data-role="nuvem-home-link"')) {
            return;
        }
        $block->setContent(self::HOME_MENU_ITEM . PHP_EOL . $content);
        $this->blockRepository->save($block);
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [CreateHowItsMadePage::class, EnablePublicGallery::class, CreateNuvemCategory::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
