<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Block\Adminhtml\Component;

use Magento\Backend\Block\Template;

class Index extends Template
{
    public function canEdit(): bool
    {
        return $this->_authorization->isAllowed(
            'Nuvem_AtelieAdmin::components_edit'
        );
    }

    public function getNewUrl(): string
    {
        return $this->getUrl(
            'nuvem_atelieadmin/component/new'
        );
    }
}