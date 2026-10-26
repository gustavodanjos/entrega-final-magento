<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Block\Adminhtml\Production;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Nuvem\AtelieAdmin\Model\Source\ProductionStatus;

class Sheet extends Template
{
    public function __construct(
        Context $context,
        private readonly ProductionStatus $statuses,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSheet(): array
    {
        return $this->getData('sheet') ?? [];
    }

    public function getSnapshot(): array
    {
        return $this->getSheet()['snapshot'] ?? [];
    }

    public function getStatusLabel(): string
    {
        return $this->statuses->getLabel((string) ($this->getSheet()['status'] ?? ''));
    }

    public function getPreviewUrl(): string
    {
        return $this->getUrl('nuvem_atelieadmin/production/preview', [
            'ficha_id' => $this->getSheet()['ficha_id']
        ]);
    }
}
