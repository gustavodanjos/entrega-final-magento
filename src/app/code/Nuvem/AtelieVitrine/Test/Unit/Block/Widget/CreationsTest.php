<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Test\Unit\Block\Widget;

use DOMDocument;
use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;
use Nuvem\AtelieStudio\Api\PreviewInterface;
use Nuvem\AtelieVitrine\Block\Widget\Creations;
use Nuvem\AtelieVitrine\Model\SavedCreation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CreationsTest extends TestCase
{
    #[DataProvider('templates')]
    public function testCardsRenderTheirOwnSignedPreviewsAndEscapeCreationNames(string $template): void
    {
        $names = ['Nuvem "azul" & <script>alert(1)</script>', 'Meu segundo tênis'];
        $configurations = [
            '{"tamanho":38,"gravacao":"BIA","selecoes":[]}',
            '{"tamanho":39,"gravacao":null,"selecoes":[]}',
        ];
        $urls = ['/atelie/preview/svg?c=first&h=signature-one', '/atelie/preview/svg?c=second&h=signature-two'];
        $creations = [];
        foreach ($names as $index => $name) {
            $creation = $this->getMockBuilder(SavedCreation::class)->disableOriginalConstructor()
                ->onlyMethods([])->getMock();
            $creation->setData(['nome' => $name, 'configuracao' => $configurations[$index]]);
            $creations[] = $creation;
        }

        $preview = $this->createMock(PreviewInterface::class);
        $preview->expects(self::exactly(2))->method('getUrl')->willReturnMap([
            [$configurations[0], $urls[0]],
            [$configurations[1], $urls[1]],
        ]);
        $escaper = $this->createEscaper();
        $block = $this->getMockBuilder(Creations::class)->disableOriginalConstructor()
            ->onlyMethods(['getCreations', 'getShowcaseTitle', 'getCreationUrl',
                'escapeHtml', 'escapeHtmlAttr', 'escapeUrl'])->getMock();
        $block->method('getCreations')->willReturn($creations);
        $block->method('getShowcaseTitle')->willReturn('Criações recentes');
        $block->method('getCreationUrl')->willReturn('/atelie/c/example');
        $block->method('escapeHtml')->willReturnCallback($escaper->escapeHtml(...));
        $block->method('escapeHtmlAttr')->willReturnCallback($escaper->escapeHtmlAttr(...));
        $block->method('escapeUrl')->willReturnCallback($escaper->escapeUrl(...));
        (new \ReflectionProperty(Creations::class, 'preview'))->setValue($block, $preview);

        ob_start();
        try {
            include $template;
            $html = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $images = $document->getElementsByTagName('img');
        self::assertCount(2, $images);
        self::assertCount(0, $document->getElementsByTagName('script'));
        foreach ($images as $index => $image) {
            self::assertSame($urls[$index], $image->getAttribute('src'));
            self::assertSame('Tênis ' . $names[$index], $image->getAttribute('alt'));
            self::assertSame('620', $image->getAttribute('width'));
            self::assertSame('290', $image->getAttribute('height'));
            self::assertSame('lazy', $image->getAttribute('loading'));
        }
    }

    public static function templates(): array
    {
        return [
            'module' => [dirname(__DIR__, 4) . '/view/frontend/templates/widget/creations.phtml'],
            'theme' => [dirname(__DIR__, 8)
                . '/app/design/frontend/Nuvem/pastel/Nuvem_AtelieVitrine/templates/widget/creations.phtml'],
        ];
    }

    private function createEscaper(): Escaper
    {
        $inline = $this->createMock(InlineInterface::class);
        $inline->method('isAllowed')->willReturn(false);
        $escaper = new Escaper();
        (new \ReflectionProperty(Escaper::class, 'translateInline'))->setValue($escaper, $inline);
        (new \ReflectionProperty(Escaper::class, 'escaper'))->setValue($escaper, new ZendEscaper());
        return $escaper;
    }
}
