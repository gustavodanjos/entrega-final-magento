<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    /**
     * Initialize dependencies.
     *
     * @param PageFactory $pageFactory
     */
    public function __construct(private readonly PageFactory $pageFactory)
    {
    }

    /**
     * Render the public gallery.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->addHandle('atelie_galeria_index');
        $page->getConfig()->getTitle()->set(__('Galeria'));
        return $page;
    }
}
