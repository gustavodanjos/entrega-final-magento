<?php
/**
 * Copyright © Nuvem Calçados. All rights reserved.
 */
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\Store;

/**
 * Seeds the storefront CMS content:
 * - the "nuvem-hero-home" block rendered by the home page hero;
 * - the "nuvem-aviso-topo" block rendered inside the lavanda top bar;
 * - the "home" CMS page, created when missing and always referencing the hero block.
 */
class AddHomeHeroCms implements DataPatchInterface
{
    private const HERO_BLOCK_IDENTIFIER = 'nuvem-hero-home';
    private const AVISO_BLOCK_IDENTIFIER = 'nuvem-aviso-topo';
    private const HOME_PAGE_IDENTIFIER = 'home';

    /**
     * @param GetBlockByIdentifierInterface $getBlockByIdentifier
     * @param GetPageByIdentifierInterface $getPageByIdentifier
     * @param BlockRepositoryInterface $blockRepository
     * @param BlockInterfaceFactory $blockFactory
     * @param PageRepositoryInterface $pageRepository
     * @param PageInterfaceFactory $pageFactory
     */
    public function __construct(
        private readonly GetBlockByIdentifierInterface $getBlockByIdentifier,
        private readonly GetPageByIdentifierInterface $getPageByIdentifier,
        private readonly BlockRepositoryInterface $blockRepository,
        private readonly BlockInterfaceFactory $blockFactory,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly PageInterfaceFactory $pageFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply(): self
    {
        $this->upsertBlock(
            self::HERO_BLOCK_IDENTIFIER,
            (string) __('Nuvem — Hero da home'),
            $this->getHeroBlockContent()
        );
        $this->upsertBlock(
            self::AVISO_BLOCK_IDENTIFIER,
            (string) __('Nuvem — Aviso do topo'),
            $this->getAvisoBlockContent()
        );
        $this->bindHeroToHomePage();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases(): array
    {
        return [];
    }

    /**
     * Creates the block when missing or refreshes its content when it already exists.
     *
     * @param string $identifier
     * @param string $title
     * @param string $content
     * @return void
     */
    private function upsertBlock(string $identifier, string $title, string $content): void
    {
        try {
            $block = $this->getBlockByIdentifier->execute($identifier, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            $block = $this->blockFactory->create();
            $block->setIdentifier($identifier);
        }

        $block->setTitle($title);
        $block->setContent($content);
        $block->setIsActive(true);
        $block->setStores([Store::DEFAULT_STORE_ID]);
        $this->blockRepository->save($block);
    }

    /**
     * Guarantees the home page references the hero block exactly once.
     *
     * @return void
     */
    private function bindHeroToHomePage(): void
    {
        try {
            $page = $this->getPageByIdentifier->execute(self::HOME_PAGE_IDENTIFIER, Store::DEFAULT_STORE_ID);
        } catch (NoSuchEntityException) {
            $page = $this->pageFactory->create();
            $page->setIdentifier(self::HOME_PAGE_IDENTIFIER);
            $page->setTitle((string) __('Ateliê Nuvem — Feito à mão'));
            $page->setPageLayout('1column');
            $page->setIsActive(true);
            $page->setStores([Store::DEFAULT_STORE_ID]);
            $page->setContent($this->getHomePageContent());
            $this->pageRepository->save($page);

            return;
        }

        if (!str_contains((string) $page->getContent(), self::HERO_BLOCK_IDENTIFIER)) {
            $page->setContent($this->getHomePageContent() . (string) $page->getContent());
            $this->pageRepository->save($page);
        }
    }

    /**
     * Hero markup kept in the CMS block so the store owner can edit it in the admin.
     *
     * @return string
     */
    private function getHeroBlockContent(): string
    {
        return <<<HTML
<div class="nuvem-hero">
    <div class="nuvem-hero__texto">
        <h1 class="nuvem-hero__titulo">Seu tênis, do seu jeito.</h1>
        <p class="nuvem-hero__resumo">
            Escolha o cabedal, o solado, os cadarços e até a gravação.
            A gente faz à mão em Franca e entrega pronto em até 12 dias úteis.
        </p>
        <a class="nuvem-hero__cta action primary" href="{{store direct_url='nuvem-runner.html'}}">
            Montar o meu tênis
        </a>
    </div>
    <img class="nuvem-hero__imagem"
         src="{{view url='Nuvem_AtelieStudio::images/tenis-completo.svg'}}"
         alt="Ilustração do tênis Nuvem, feito à mão"
         width="620"
         height="290"/>
</div>
HTML;
    }

    /**
     * Lavanda bar message kept in the CMS block so the store owner can edit it in the admin.
     *
     * @return string
     */
    private function getAvisoBlockContent(): string
    {
        return '<p class="nuvem-aviso-topo">Tênis feitos à mão em Franca · enviamos para todo o Brasil.</p>';
    }

    /**
     * Home page content: the hero block reference.
     *
     * @return string
     */
    private function getHomePageContent(): string
    {
        return '{{block class="Magento\Cms\Block\BlockByIdentifier" identifier="'
            . self::HERO_BLOCK_IDENTIFIER . '"}}' . PHP_EOL;
    }
}
