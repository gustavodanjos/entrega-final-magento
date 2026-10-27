<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Block\Adminhtml\Component;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class NewButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly UrlInterface $url
    ) {
    }

    public function getButtonData(): array
    {
        if (!$this->authorization->isAllowed('Nuvem_AtelieAdmin::components_edit')) {
            return [];
        }
        return [
            'label' => __('Novo componente'),
            'class' => 'primary',
            'on_click' => sprintf(
                "location.href = '%s';",
                $this->url->getUrl('nuvem_atelieadmin/component/new')
            ),
            'sort_order' => 10
        ];
    }
}
