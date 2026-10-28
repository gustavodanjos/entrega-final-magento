<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterfaceFactory;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use Nuvem\AtelieVitrine\Setup\Patch\Data\AddHomeHeroCms;
use Nuvem\AtelieVitrine\Setup\Patch\Data\CompleteHomeLayout;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AddHomeHeroCmsTest extends TestCase
{
    private const HERO = 'nuvem-hero-home';
    private const ANNOUNCEMENT = 'nuvem-aviso-topo';
    private const SHOWCASE = 'nuvem-criacoes-home';

    private static GeneratedClassesAutoloader $factoryAutoloader;

    private GetBlockByIdentifierInterface|MockObject $blockLookup;
    private GetPageByIdentifierInterface|MockObject $pageLookup;
    private BlockRepositoryInterface|MockObject $blockRepository;
    private BlockInterfaceFactory|MockObject $blockFactory;
    private PageRepositoryInterface|MockObject $pageRepository;
    private PageInterfaceFactory|MockObject $pageFactory;
    private AddHomeHeroCms $initialPatch;
    private CompleteHomeLayout $completePatch;
    private array $blocks = [];
    private array $savedIdentifiers = [];
    private ?Page $home = null;

    public static function setUpBeforeClass(): void
    {
        self::$factoryAutoloader = new GeneratedClassesAutoloader(
            [new FactoryGenerator()],
            new Io(new File(), sys_get_temp_dir() . '/nuvem-vitrine-unit-generated')
        );
        spl_autoload_register([self::$factoryAutoloader, 'load']);
    }

    public static function tearDownAfterClass(): void
    {
        spl_autoload_unregister([self::$factoryAutoloader, 'load']);
    }

    protected function setUp(): void
    {
        $this->blockLookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $this->pageLookup = $this->createMock(GetPageByIdentifierInterface::class);
        $this->blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $this->blockFactory = $this->createMock(BlockInterfaceFactory::class);
        $this->pageRepository = $this->createMock(PageRepositoryInterface::class);
        $this->pageFactory = $this->createMock(PageInterfaceFactory::class);

        $this->blockLookup->method('execute')->willReturnCallback(function (string $identifier, int $storeId): Block {
            self::assertSame(0, $storeId);
            if (!isset($this->blocks[$identifier])) {
                throw new NoSuchEntityException(__('Block not found.'));
            }
            return $this->blocks[$identifier];
        });
        $this->pageLookup->method('execute')->willReturnCallback(function (string $identifier, int $storeId): Page {
            self::assertSame('home', $identifier);
            self::assertSame(0, $storeId);
            if ($this->home === null) {
                throw new NoSuchEntityException(__('Page not found.'));
            }
            return $this->home;
        });
        $this->blockFactory->method('create')->willReturnCallback(fn (): Block => $this->createBlock());
        $this->pageFactory->method('create')->willReturnCallback(fn (): Page => $this->createPage());
        $this->blockRepository->method('save')->willReturnCallback(function (Block $block): Block {
            if (!$block->getId()) {
                $block->setId(count($this->blocks) + 1);
            }
            $identifier = (string) $block->getIdentifier();
            $this->blocks[$identifier] = $block;
            $this->savedIdentifiers[] = $identifier;
            return $block;
        });
        $this->pageRepository->method('save')->willReturnCallback(function (Page $page): Page {
            $this->home = $page;
            return $page;
        });

        $this->initialPatch = new AddHomeHeroCms(
            $this->blockLookup,
            $this->pageLookup,
            $this->blockRepository,
            $this->blockFactory,
            $this->pageRepository,
            $this->pageFactory
        );
        $this->completePatch = new CompleteHomeLayout(
            $this->blockLookup,
            $this->blockRepository,
            $this->blockFactory,
            $this->pageLookup,
            $this->pageRepository
        );
    }

    public function testExistingBlocksAreNotSavedOrChanged(): void
    {
        $hero = $this->addCustomBlock(self::HERO, 11);
        $announcement = $this->addCustomBlock(self::ANNOUNCEMENT, 12);
        $expectedHero = $hero->getData();
        $expectedAnnouncement = $announcement->getData();
        $this->blockRepository->expects(self::never())->method('save');
        $this->blockFactory->expects(self::never())->method('create');

        $this->initialPatch->apply();

        self::assertSame($expectedHero, $hero->getData());
        self::assertSame($expectedAnnouncement, $announcement->getData());
    }

    public function testMissingBlocksAndHomeAreCreatedWithDefaults(): void
    {
        self::assertSame($this->initialPatch, $this->initialPatch->apply());

        self::assertSame([self::HERO, self::ANNOUNCEMENT], $this->savedIdentifiers);
        foreach ($this->blocks as $identifier => $block) {
            self::assertSame($identifier, $block->getIdentifier());
            self::assertNotEmpty($block->getTitle());
            self::assertTrue($block->isActive());
            self::assertSame([0], $block->getStores());
            self::assertNotEmpty($block->getContent());
        }
        self::assertSame('home', $this->home->getIdentifier());
        self::assertSame('1column', $this->home->getPageLayout());
        self::assertTrue($this->home->isActive());
        self::assertSame([0], $this->home->getStores());
        self::assertSame(1, substr_count($this->home->getContent(), self::HERO));
    }

    #[DataProvider('existingBlockIdentifiers')]
    public function testOnlyTheMissingBlockIsCreated(string $existingIdentifier, string $missingIdentifier): void
    {
        $existing = $this->addCustomBlock($existingIdentifier, 21);
        $expected = $existing->getData();

        $this->initialPatch->apply();

        self::assertSame([$missingIdentifier], $this->savedIdentifiers);
        self::assertSame($expected, $existing->getData());
        self::assertCount(2, $this->blocks);
    }

    public static function existingBlockIdentifiers(): array
    {
        return [
            'existing hero' => [self::HERO, self::ANNOUNCEMENT],
            'existing announcement' => [self::ANNOUNCEMENT, self::HERO]
        ];
    }

    public function testFullSequencePreservesCustomBlocksAndExistingPageMetadata(): void
    {
        $hero = $this->addCustomBlock(self::HERO, 31);
        $announcement = $this->addCustomBlock(self::ANNOUNCEMENT, 32);
        $expectedHero = $hero->getData();
        $expectedAnnouncement = $announcement->getData();
        $this->home = $this->createPage();
        $this->home->setData([
            'page_id' => 41,
            'identifier' => 'home',
            'title' => 'Minha página inicial',
            'page_layout' => '2columns-left',
            'is_active' => false,
            'stores' => [2],
            'content' => '<p>Conteúdo editorial da loja</p>'
        ]);
        $expectedPageMetadata = $this->home->getData();
        unset($expectedPageMetadata['content']);

        $this->initialPatch->apply();
        $this->completePatch->apply();

        self::assertSame($expectedHero, $hero->getData());
        self::assertSame($expectedAnnouncement, $announcement->getData());
        self::assertNotContains(self::HERO, $this->savedIdentifiers);
        self::assertNotContains(self::ANNOUNCEMENT, $this->savedIdentifiers);
        self::assertStringContainsString('<p>Conteúdo editorial da loja</p>', $this->home->getContent());
        self::assertSame(1, substr_count($this->home->getContent(), self::HERO));
        self::assertSame(1, substr_count($this->home->getContent(), self::SHOWCASE));
        $actualPageMetadata = $this->home->getData();
        unset($actualPageMetadata['content']);
        self::assertSame($expectedPageMetadata, $actualPageMetadata);
    }

    public function testFreshInstallReceivesTheUpdatedHero(): void
    {
        $this->initialPatch->apply();
        $originalContent = $this->blocks[self::HERO]->getContent();

        $this->completePatch->apply();

        self::assertNotSame($originalContent, $this->blocks[self::HERO]->getContent());
        self::assertStringContainsString('Do seu jeito.</span>', $this->blocks[self::HERO]->getContent());
        self::assertSame(1, substr_count($this->home->getContent(), self::HERO));
        self::assertSame(1, substr_count($this->home->getContent(), self::SHOWCASE));
        self::assertCount(4, $this->blocks);
    }

    public function testOriginalHeroIsUpdatedWithoutChangingItsMetadata(): void
    {
        $this->initialPatch->apply();
        $hero = $this->blocks[self::HERO];
        $hero->setTitle('Título escolhido pela loja');
        $hero->setIsActive(false);
        $hero->setStores([2]);
        $metadata = $hero->getData();
        unset($metadata['content']);

        $this->initialPatch->apply();
        $this->completePatch->apply();

        self::assertStringContainsString('Do seu jeito.</span>', $hero->getContent());
        $actualMetadata = $hero->getData();
        unset($actualMetadata['content']);
        self::assertSame($metadata, $actualMetadata);
    }

    public function testReappliedSequencePreservesLaterEditsWithoutDuplicates(): void
    {
        $this->initialPatch->apply();
        $this->completePatch->apply();
        $hero = $this->blocks[self::HERO];
        $announcement = $this->blocks[self::ANNOUNCEMENT];
        $hero->setContent('<p>Hero editado depois da instalação</p>');
        $announcement->setContent('<p>Aviso editado depois da instalação</p>');
        $this->home->setContent($this->home->getContent() . '<p>Outra seção</p>');
        $expectedHero = $hero->getData();
        $expectedAnnouncement = $announcement->getData();
        $expectedHome = $this->home->getData();

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->initialPatch->apply();
            $this->completePatch->apply();
        }

        self::assertSame($expectedHero, $hero->getData());
        self::assertSame($expectedAnnouncement, $announcement->getData());
        self::assertSame($expectedHome, $this->home->getData());
        self::assertCount(4, $this->blocks);
    }

    private function addCustomBlock(string $identifier, int $id): Block
    {
        $block = $this->createBlock();
        $block->setData([
            'block_id' => $id,
            'identifier' => $identifier,
            'title' => 'Título personalizado: ' . $identifier,
            'content' => '<p>Conteúdo personalizado: ' . $identifier . '</p>',
            'is_active' => false,
            'stores' => [2]
        ]);
        $this->blocks[$identifier] = $block;
        return $block;
    }

    private function createBlock(): Block
    {
        return $this->getMockBuilder(Block::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }

    private function createPage(): Page
    {
        return $this->getMockBuilder(Page::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
    }
}
