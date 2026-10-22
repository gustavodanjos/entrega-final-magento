<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Creation;

use Magento\Customer\Controller\AbstractAccount;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends AbstractAccount implements HttpGetActionInterface
{
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->pageFactory->create();

        $page->getConfig()->getTitle()->set(
            __('Minhas Criações')
        );

        return $page;
    }
}
