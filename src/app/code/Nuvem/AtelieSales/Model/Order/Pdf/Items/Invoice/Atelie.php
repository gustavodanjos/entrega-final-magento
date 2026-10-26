<?php
declare(strict_types=1);

namespace Nuvem\AtelieSales\Model\Order\Pdf\Items\Invoice;

use Magento\Framework\Filter\FilterManager;
use Magento\Framework\Filesystem;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Sales\Model\Order\Pdf\Items\Invoice\DefaultInvoice;
use Magento\Sales\Model\RtlTextHandler;
use Magento\Tax\Helper\Data as TaxData;
use Nuvem\AtelieSales\Model\Order\ItemOptionsReader;

class Atelie extends DefaultInvoice
{
    private const OPTION_LINE_HEIGHT = 12;

    private const OPTION_TEXT_WIDTH = 40;
    private const OPTION_LABEL_FEED = 35;
    private const OPTION_VALUE_FEED = 40;

    private bool $drawingProductRow = false;

    public function __construct(
        Context $context,
        Registry $registry,
        TaxData $taxData,
        Filesystem $filesystem,
        FilterManager $filterManager,
        StringUtils $string,
        private readonly ItemOptionsReader $optionsReader,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
        ?RtlTextHandler $rtlTextHandler = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $taxData,
            $filesystem,
            $filterManager,
            $string,
            $resource,
            $resourceCollection,
            $data,
            $rtlTextHandler
        );
    }

    /**
     * Use historical options while retaining native PDF pagination and totals.
     *
     * @return array
     */
    public function getItemOptions(): array
    {
        return $this->drawingProductRow ? [] : $this->optionsReader->read($this->getItem());
    }

    /**
     * Draw each option line separately: Magento 2.4.8 can reuse the previous page
     * when its default renderer splits a long options block internally.
     *
     * @return void
     */
    public function draw()
    {
        $this->drawingProductRow = true;
        try {
            parent::draw();
        } finally {
            $this->drawingProductRow = false;
        }

        foreach ($this->getItemOptions() as $option) {
            $this->drawOptionText((string) $option['label'], self::OPTION_LABEL_FEED, 'italic');
            $this->drawOptionText((string) ($option['print_value'] ?? $option['value']), self::OPTION_VALUE_FEED, 'regular');
        }
    }

    /**
     * Keep the current page returned by the native line renderer at every boundary.
     *
     * @param string $text
     * @param int $feed
     * @param string $font
     * @return void
     */
    private function drawOptionText(string $text, int $feed, string $font): void
    {
        foreach ($this->string->split($text, self::OPTION_TEXT_WIDTH, true, true) as $line) {
            $page = $this->getPdf()->drawLineBlocks($this->getPage(), [[
                'lines' => [[['text' => $line, 'feed' => $feed, 'font' => $font]]],
                'height' => self::OPTION_LINE_HEIGHT,
            ]], ['table_header' => true]);
            $this->setPage($page);
        }
    }
}
