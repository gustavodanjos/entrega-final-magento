<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Block\Adminhtml\Rule\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly Context $context
    ) {
    }

    public function getButtonData(): array
    {
        $url = $this->context
            ->getUrlBuilder()
            ->getUrl('nuvem_atelieadmin/rule/index');

        return [
            'label' => __('Voltar'),
            'class' => 'back',
            'sort_order' => 10,
            'on_click' => 'location.href = ' . json_encode(
                $url,
                JSON_HEX_TAG
                | JSON_HEX_APOS
                | JSON_HEX_AMP
                | JSON_HEX_QUOT
                | JSON_THROW_ON_ERROR
            ),
        ];
    }
}