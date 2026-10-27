<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;

class Liked implements HttpGetActionInterface
{
    /**
     * Initialize the personal likes page.
     *
     * @param PageFactory $pageFactory
     */
    public function __construct(private readonly PageFactory $pageFactory)
    {
    }

    /**
     * Render the private likes gallery.
     *
     * @return Page
     */
    public function execute(): Page
    {
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Curtidas por mim'));
        return $page;
    }
}
