<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Setup\Patch\Data\UpdateHomePresentation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UpdateHomePresentationTest extends TestCase
{
    #[DataProvider('metaTitles')]
    public function testMigrationPreservesEditorialContentAndBrowserTitleAndIsIdempotent(string $metaTitle): void
    {
        $hero = '{{block class="Magento\\Cms\\Block\\BlockByIdentifier" identifier="nuvem-hero-home"}}';
        $legacy = '{{block class="Magento\\Cms\\Block\\BlockByIdentifier" identifier="nuvem-criacoes-home"}}';
        $widget = '<div data-content-type="nuvem_creations" data-showcase-title="Criações da semana">'
            . '{{widget type="Nuvem\\AtelieVitrine\\Block\\Widget\\Creations" title="Criações da semana" limit="4"}}'
            . '</div>';
        $page = $this->createPage($hero . $legacy . $widget, $metaTitle);
        $menu = $this->getMockBuilder(Block::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $menuContent = '<li><a href="/galeria">Galeria</a></li>'
            . '<li><a href="/custom">Meu conteúdo</a></li>';
        $menu->setContent($menuContent);
        $legacyBlock = $this->getMockBuilder(Block::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $legacyBlock->setIsActive(true)->setContent('<p>Exemplos editoriais</p>');
        $lookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $lookup->method('execute')->willReturnCallback(
            static fn ($identifier) => $identifier === 'nuvem-menu' ? $menu : $legacyBlock
        );
        $blocks = $this->createMock(BlockRepositoryInterface::class);
        $blocks->expects(self::exactly(2))->method('save')->willReturnArgument(0);
        $pages = $this->createMock(PageRepositoryInterface::class);
        $pages->expects(self::once())->method('save')->with($page)->willReturn($page);
        $patch = $this->createPatch($page, $pages, $lookup, $blocks);

        self::assertSame($patch, $patch->apply());
        $patch->apply();

        self::assertSame($hero . $widget, $page->getContent());
        self::assertSame('Início', $page->getTitle());
        self::assertSame($metaTitle ?: 'Home Page', $page->getMetaTitle());
        self::assertSame('Título editorial', $page->getContentHeading());
        self::assertSame('home', $page->getIdentifier());
        self::assertFalse($legacyBlock->isActive());
        self::assertSame('<p>Exemplos editoriais</p>', $legacyBlock->getContent());
        self::assertStringEndsWith($menuContent, $menu->getContent());
        self::assertStringStartsWith('<li class="level0 level-top" data-role="nuvem-home-link">', $menu->getContent());
        self::assertStringContainsString("href=\"{{store direct_url=''}}\"><span>Início</span>", $menu->getContent());
        self::assertSame(1, substr_count($menu->getContent(), 'data-role="nuvem-home-link"'));
    }

    public function testMissingLegacyAndMenuBlocksDoNotPreventRenamingHome(): void
    {
        $page = $this->createPage('<p>Conteúdo personalizado</p>', 'Título SEO');
        $lookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $lookup->method('execute')->willThrowException(new NoSuchEntityException(__('Block not found.')));
        $blocks = $this->createMock(BlockRepositoryInterface::class);
        $blocks->expects(self::never())->method('save');
        $pages = $this->createMock(PageRepositoryInterface::class);
        $pages->expects(self::once())->method('save')->willReturn($page);

        $this->createPatch($page, $pages, $lookup, $blocks)->apply();

        self::assertSame('Início', $page->getTitle());
        self::assertSame('Título SEO', $page->getMetaTitle());
        self::assertSame('<p>Conteúdo personalizado</p>', $page->getContent());
    }

    public static function metaTitles(): array
    {
        return ['empty meta title' => [''], 'custom meta title' => ['Nuvem — Seu tênis, do seu jeito']];
    }

    private function createPage(string $content, string $metaTitle): Page
    {
        $page = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $page->setTitle('Home Page')->setMetaTitle($metaTitle)->setContent($content)
            ->setContentHeading('Título editorial')->setIdentifier('home');
        return $page;
    }

    private function createPatch(
        Page $page,
        PageRepositoryInterface $pages,
        GetBlockByIdentifierInterface $lookup,
        BlockRepositoryInterface $blocks
    ): UpdateHomePresentation {
        $pageLookup = $this->createMock(GetPageByIdentifierInterface::class);
        $pageLookup->method('execute')->with('home', 0)->willReturn($page);
        return new UpdateHomePresentation($pageLookup, $pages, $lookup, $blocks);
    }
}
