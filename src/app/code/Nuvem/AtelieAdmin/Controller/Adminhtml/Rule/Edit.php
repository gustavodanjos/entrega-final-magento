<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Nuvem\Atelie\Api\RuleRepositoryInterface;

class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::rules';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly RuleRepositoryInterface $ruleRepository
    ) {
        parent::__construct($context);
    }

    public function execute(): Page|Redirect
    {
        $rawId = $this->getRequest()->getParam('regra_id');
        $title = __('Nova regra');

        if ($rawId !== null && $rawId !== '') {
            $ruleId = is_scalar($rawId)
                ? filter_var(
                    $rawId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                )
                : false;

            if ($ruleId === false) {
                $this->messageManager->addErrorMessage(
                    __('O identificador da regra é inválido.')
                );

                return $this->resultRedirectFactory
                    ->create()
                    ->setPath('*/*/index');
            }

            try {
                $rule = $this->ruleRepository->getById((int) $ruleId);
                $title = __('Editar regra: %1', $rule->getCodigo());
            } catch (NoSuchEntityException $exception) {
                $this->messageManager->addErrorMessage(
                    __('A regra informada não existe mais.')
                );

                return $this->resultRedirectFactory
                    ->create()
                    ->setPath('*/*/index');
            }
        }

        $page = $this->pageFactory->create();

        $page->setActiveMenu('Nuvem_AtelieAdmin::rules_menu');
        $page->getConfig()->getTitle()->prepend($title);

        return $page;
    }
}