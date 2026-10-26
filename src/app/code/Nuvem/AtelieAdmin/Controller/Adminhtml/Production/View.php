<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Production;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Result\PageFactory;
use Nuvem\AtelieAdmin\Model\Production\SheetProvider;
use Psr\Log\LoggerInterface;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::production';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly SheetProvider $sheetProvider,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $id = filter_var($this->getRequest()->getParam('ficha_id'), FILTER_VALIDATE_INT);
            $sheet = $this->sheetProvider->getById($id === false ? 0 : (int) $id);
            $page = $this->pageFactory->create();
            $page->setActiveMenu('Nuvem_AtelieAdmin::production_menu');
            $page->getConfig()->getTitle()->prepend(__('Ficha de produção #%1', $sheet['ficha_id']));
            $page->getLayout()->getBlock('nuvem.atelie.production.sheet')->setData('sheet', $sheet);
            return $page;
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Atelie: failed to open production sheet.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('Não foi possível abrir a ficha.'));
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
