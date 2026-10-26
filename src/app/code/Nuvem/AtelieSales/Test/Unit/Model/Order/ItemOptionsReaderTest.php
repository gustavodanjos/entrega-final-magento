<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Test\Unit\Model\Order;

use Magento\Sales\Model\Order\Item;
use Magento\Sales\Model\Order\Invoice\Item as InvoiceItem;
use Magento\Sales\Model\Order\Creditmemo\Item as CreditmemoItem;
use Nuvem\AtelieSales\Model\Order\ItemOptionsReader;
use PHPUnit\Framework\TestCase;

class ItemOptionsReaderTest extends TestCase
{
    public function testSnapshotKeepsHistoricalColorsAndTextAcrossDocuments(): void
    {
        $snapshot = [
            ['label' => 'Tamanho', 'value' => '38'],
            ['label' => 'Gravação', 'value' => '<b>LUIZ</b>'],
            ['label' => 'Cabedal', 'value' => 'Lavanda original', 'cor_hex' => '#DCCFF7'],
        ];
        $item = $this->item([
            ItemOptionsReader::SNAPSHOT_KEY => $snapshot,
            'additional_options' => $snapshot,
            'attributes_info' => $snapshot,
        ]);
        $reader = new ItemOptionsReader();
        $expected = $reader->read($item);
        $this->assertCount(3, $expected);
        $this->assertSame('<b>LUIZ</b>', $expected[1]['value']);
        $this->assertSame('#DCCFF7', $expected[2]['cor_hex']);
        foreach ([InvoiceItem::class, CreditmemoItem::class] as $class) {
            $documentItem = $this->getMockBuilder($class)->disableOriginalConstructor()
                ->onlyMethods(['getOrderItem'])->getMock();
            $documentItem->method('getOrderItem')->willReturn($item);
            $this->assertSame($expected, $reader->read($documentItem));
        }
    }

    public function testLegacyMirrorsDoNotDuplicateRowsOrRemoveOtherOptions(): void
    {
        $size = ['label' => 'Tamanho', 'value' => '38'];
        $extra = ['label' => 'Observação', 'value' => 'Texto adicional'];
        $item = $this->item([
            'options' => [$size, $extra],
            'additional_options' => [$size, $extra, $extra],
            'attributes_info' => [$size, $extra, $extra, ['label' => 'Outra', 'value' => 'Única']],
        ]);
        $rows = (new ItemOptionsReader())->read($item);
        $this->assertCount(6, $rows);
        $this->assertSame('Única', $rows[5]['value']);
        $this->assertSame($rows[3], $rows[4]);
    }

    public function testSnapshotPreservesAdditionalOptionsOutsideTheFicha(): void
    {
        $size = ['label' => 'Tamanho', 'value' => '38'];
        $item = $this->item([
            ItemOptionsReader::SNAPSHOT_KEY => [$size],
            'additional_options' => [$size, ['label' => 'Extra', 'value' => 'Separada']],
            'attributes_info' => [$size],
        ]);
        $this->assertSame(['38', 'Separada'], array_column((new ItemOptionsReader())->read($item), 'value'));
    }

    public function testInvalidOrAbsentColorsCannotBecomeInlineStyles(): void
    {
        foreach ([null, '', '#FFF', 'red', '#FFFFFF;display:none', '\" onmouseover=alert(1)'] as $color) {
            $item = $this->item([
                'additional_options' => [['label' => 'Cabedal', 'value' => 'Branco', 'cor_hex' => $color]],
            ]);
            $row = (new ItemOptionsReader())->read($item)[0];
            $this->assertNull($row['cor_hex']);
            $this->assertSame('Branco', $row['value']);
        }
    }

    public function testMissingAndSingleLegacyListsRemainReadable(): void
    {
        $reader = new ItemOptionsReader();
        $this->assertSame([], $reader->read($this->item([])));
        foreach (['additional_options', 'attributes_info'] as $key) {
            $rows = $reader->read($this->item([$key => [['label' => 'Tamanho', 'value' => '38']]]));
            $this->assertCount(1, $rows);
            $this->assertNull($rows[0]['cor_hex']);
        }
    }

    private function item(array $options): Item
    {
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()
            ->onlyMethods(['getProductOptions'])->getMock();
        $item->method('getProductOptions')->willReturn($options);
        return $item;
    }
}
