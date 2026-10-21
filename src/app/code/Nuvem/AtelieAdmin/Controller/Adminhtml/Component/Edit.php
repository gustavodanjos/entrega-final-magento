<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Component;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::components_edit';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly ComponentRepositoryInterface $componentRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Page|Redirect
    {
        $rawId = $this->getRequest()->getParam('componente_id');
        $title = __('Novo componente');

        if ($rawId !== null && $rawId !== '') {
            $componentId = is_scalar($rawId)
                ? filter_var(
                    $rawId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                )
                : false;

            if ($componentId === false) {
                $this->messageManager->addErrorMessage(
                    __('O identificador do componente é inválido.')
                );

                return $this->resultRedirectFactory
                    ->create()
                    ->setPath('*/*/index');
            }

            try {
                $component = $this->componentRepository->getById(
                    (int) $componentId
                );

                $title = __('Editar componente: %1', $component->getNome());
            } catch (NoSuchEntityException $exception) {
                $this->messageManager->addErrorMessage(
                    __('O componente informado não existe mais.')
                );

                return $this->resultRedirectFactory
                    ->create()
                    ->setPath('*/*/index');
            }
        }

        $page = $this->pageFactory->create();

        $page->setActiveMenu('Nuvem_AtelieAdmin::components_menu');
        $page->getConfig()->getTitle()->prepend($title);

        return $page;
    }
}