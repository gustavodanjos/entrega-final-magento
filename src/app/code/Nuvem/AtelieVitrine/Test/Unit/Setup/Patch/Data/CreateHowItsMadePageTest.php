<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\PageInterfaceFactory;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Magento\Cms\Api\GetPageByIdentifierInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\Block;
use Magento\Cms\Model\Page;
use Magento\Framework\Code\Generator\Io;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\TestFramework\Unit\Autoloader\FactoryGenerator;
use Magento\Framework\TestFramework\Unit\Autoloader\GeneratedClassesAutoloader;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\AtelieVitrine\Setup\Patch\Data\CreateHowItsMadePage;
use PHPUnit\Framework\TestCase;

class CreateHowItsMadePageTest extends TestCase
{
    /** @var GeneratedClassesAutoloader */
    private static GeneratedClassesAutoloader $factoryAutoloader;

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

    public function testCreatesAnActivePageForAllStoresOnlyOnce(): void
    {
        $savedPage = null;
        $page = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $pageLookup = $this->createMock(GetPageByIdentifierInterface::class);
        $pageLookup->method('execute')->with('como-e-feito', 0)->willReturnCallback(
            static function () use (&$savedPage): Page {
                if ($savedPage === null) {
                    throw new NoSuchEntityException();
                }
                return $savedPage;
            }
        );
        $pageFactory = $this->createMock(PageInterfaceFactory::class);
        $pageFactory->expects(self::once())->method('create')->willReturn($page);
        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->expects(self::once())->method('save')->willReturnCallback(
            static function (Page $page) use (&$savedPage): Page {
                $savedPage = $page;
                return $page;
            }
        );
        $blockLookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $blockLookup->method('execute')->willThrowException(new NoSuchEntityException());
        $blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $patch = new CreateHowItsMadePage(
            $pageLookup,
            $pageFactory,
            $pageRepository,
            $blockLookup,
            $blockRepository
        );

        $patch->apply();
        $patch->apply();

        self::assertSame('Como é feito', $savedPage->getTitle());
        self::assertSame('Como é feito', $savedPage->getContentHeading());
        self::assertSame('como-e-feito', $savedPage->getIdentifier());
        self::assertSame('1column', $savedPage->getPageLayout());
        self::assertTrue($savedPage->isActive());
        self::assertSame([0], $savedPage->getStores());
        self::assertStringContainsString('catorze artesãos', $savedPage->getContent());
    }

    public function testRepeatedApplicationPreservesEditorialPageAndNavigation(): void
    {
        $page = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->getMock();
        $pageLookup = $this->createMock(GetPageByIdentifierInterface::class);
        $pageLookup->expects(self::exactly(2))->method('execute')->with('como-e-feito', 0)->willReturn($page);
        $pageFactory = $this->createMock(PageInterfaceFactory::class);
        $pageFactory->expects(self::never())->method('create');
        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->expects(self::never())->method('save');
        $menu = $this->getMockBuilder(Block::class)->disableOriginalConstructor()
            ->onlyMethods(['getContent'])->getMock();
        $menu->method('getContent')->willReturn('<li><a href="/our-story">Nossa história</a></li>');
        $blockLookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $blockLookup->method('execute')->with('nuvem-menu', 0)->willReturn($menu);
        $blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $blockRepository->expects(self::never())->method('save');
        $patch = new CreateHowItsMadePage(
            $pageLookup,
            $pageFactory,
            $pageRepository,
            $blockLookup,
            $blockRepository
        );

        $patch->apply();
        $patch->apply();
    }

    public function testActivatesOnlyTheDefaultNavigationPlaceholder(): void
    {
        $page = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->getMock();
        $pageLookup = $this->createMock(GetPageByIdentifierInterface::class);
        $pageLookup->method('execute')->willReturn($page);
        $pageFactory = $this->createMock(PageInterfaceFactory::class);
        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->expects(self::never())->method('save');
        $menu = $this->getMockBuilder(Block::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $otherLink = '<li><a href="/custom">Custom</a></li>';
        $menu->setContent($otherLink . '<li><span class="level-top nuvem-navigation__pending"'
            . ' aria-disabled="true" title="Em breve">Como é feito</span></li>');
        $blockLookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $blockLookup->method('execute')->willReturn($menu);
        $blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $blockRepository->expects(self::once())->method('save')->with(self::callback(
            static fn (Block $block): bool => $block->getContent() === $otherLink
                . '<li><a class="level-top" href="{{store direct_url=\'como-e-feito\'}}">'
                . '<span>Como é feito</span></a></li>'
        ))->willReturn($menu);
        $patch = new CreateHowItsMadePage(
            $pageLookup,
            $pageFactory,
            $pageRepository,
            $blockLookup,
            $blockRepository
        );

        $patch->apply();
        $patch->apply();
    }

    public function testExistingPageDoesNotRequireNavigationBlock(): void
    {
        $page = $this->getMockBuilder(Page::class)->disableOriginalConstructor()->getMock();
        $pageLookup = $this->createMock(GetPageByIdentifierInterface::class);
        $pageLookup->method('execute')->willReturn($page);
        $pageFactory = $this->createMock(PageInterfaceFactory::class);
        $pageRepository = $this->createMock(PageRepositoryInterface::class);
        $pageRepository->expects(self::never())->method('save');
        $blockLookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $blockLookup->method('execute')->willThrowException(new NoSuchEntityException());
        $blockRepository = $this->createMock(BlockRepositoryInterface::class);
        $blockRepository->expects(self::never())->method('save');
        $patch = new CreateHowItsMadePage(
            $pageLookup,
            $pageFactory,
            $pageRepository,
            $blockLookup,
            $blockRepository
        );

        self::assertSame($patch, $patch->apply());
    }
}
