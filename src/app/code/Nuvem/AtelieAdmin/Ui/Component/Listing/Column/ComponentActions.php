<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

class ComponentActions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if ($this->authorization->isAllowed('Nuvem_AtelieAdmin::components_edit')) {
            foreach ($dataSource['data']['items'] ?? [] as $key => $item) {
                $dataSource['data']['items'][$key][$this->getData('name')] = [
                    'edit' => [
                        'href' => $this->context->getUrl(
                            'nuvem_atelieadmin/component/edit',
                            ['componente_id' => $item['componente_id']]
                        ),
                        'label' => __('Editar')
                    ]
                ];
            }
        }
        return $dataSource;
    }
}
