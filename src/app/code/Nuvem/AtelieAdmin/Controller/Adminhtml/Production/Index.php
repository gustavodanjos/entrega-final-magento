<?php

declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Production;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::production';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Nuvem_AtelieAdmin::production_menu');
        $page->getConfig()->getTitle()->prepend(__('Produção'));

        return $page;
    }
}
