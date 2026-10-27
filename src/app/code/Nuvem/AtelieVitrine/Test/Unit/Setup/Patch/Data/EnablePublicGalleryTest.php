<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Setup\Patch\Data;

use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Api\Data\BlockInterface;
use Magento\Cms\Api\GetBlockByIdentifierInterface;
use Nuvem\AtelieVitrine\Setup\Patch\Data\EnablePublicGallery;
use Nuvem\AtelieVitrine\Setup\Patch\Data\UnlinkPendingGallery;
use PHPUnit\Framework\TestCase;

class EnablePublicGalleryTest extends TestCase
{
    public function testCustomEditorialContentIsNeverSaved(): void
    {
        $lookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $repository = $this->createMock(BlockRepositoryInterface::class);
        $block = $this->createMock(BlockInterface::class);
        $block->method('getContent')->willReturn('<p>Conteúdo personalizado da loja</p>');
        $block->expects(self::never())->method('setContent');
        $lookup->expects(self::exactly(2))->method('execute')->willReturn($block);
        $repository->expects(self::never())->method('save');
        $patch = new EnablePublicGallery($lookup, $repository);
        self::assertSame($patch, $patch->apply());
        self::assertContains(UnlinkPendingGallery::class, EnablePublicGallery::getDependencies());
    }

    public function testOnlyKnownPlaceholderChangesAndSecondApplicationDoesNotSave(): void
    {
        $lookup = $this->createMock(GetBlockByIdentifierInterface::class);
        $repository = $this->createMock(BlockRepositoryInterface::class);
        $block = $this->createMock(BlockInterface::class);
        $content = '<p>Editorial</p>'
            . '<span class="level-top nuvem-navigation__pending" aria-disabled="true" title="Em breve">Galeria</span>';
        $block->method('getContent')->willReturnCallback(static function () use (&$content) {
            return $content;
        });
        $block->expects(self::once())->method('setContent')->willReturnCallback(
            static function ($updated) use (&$content, $block) {
                $content = $updated;
                return $block;
            }
        );
        $lookup->method('execute')->willReturn($block);
        $repository->expects(self::once())->method('save')->with($block)->willReturn($block);
        $patch = new EnablePublicGallery($lookup, $repository);
        $patch->apply();
        $patch->apply();
        self::assertStringContainsString('<p>Editorial</p>', $content);
        self::assertStringContainsString("direct_url='galeria'", $content);
    }
}
