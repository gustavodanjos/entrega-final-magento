<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Ui\Component\Listing;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Listing;

class ComponentListing extends Listing
{
    public function __construct(
        ContextInterface $context,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $components, $data);
    }

    public function prepare()
    {
        parent::prepare();
        if ($this->authorization->isAllowed('Nuvem_AtelieAdmin::components_edit')) {
            return;
        }
        $columns = $this->getComponent('component_columns');
        if ($columns) {
            $config = $columns->getData('config') ?: [];
            $config['editorConfig']['enabled'] = false;
            $config['childDefaults']['fieldAction'] = false;
            $columns->setData('config', $config);
        }
        $toolbar = $this->getComponent('listing_top');
        $massaction = $toolbar ? $toolbar->getComponent('listing_massaction') : null;
        if ($massaction) {
            $config = $massaction->getData('config') ?: [];
            $config['visible'] = false;
            $massaction->setData('config', $config);
        }
    }
}
