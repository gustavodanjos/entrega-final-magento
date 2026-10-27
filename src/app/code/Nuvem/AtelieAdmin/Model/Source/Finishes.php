<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Nuvem\Atelie\Model\ResourceModel\Component\CollectionFactory;

class Finishes implements OptionSourceInterface
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        $collection = $this->collectionFactory->create();
        $collection->getSelect()->reset(\Magento\Framework\DB\Select::COLUMNS)
            ->columns(['acabamento'])->distinct()->order('acabamento ASC');
        $options = [];
        foreach ($collection->getConnection()->fetchCol($collection->getSelect()) as $finish) {
            $options[] = ['value' => $finish, 'label' => $finish];
        }
        return $options;
    }
}
