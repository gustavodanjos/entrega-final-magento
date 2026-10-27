<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Production;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\UiComponent\DataProvider\CollectionFactory;
use Magento\Ui\Component\MassAction\Filter;
use Nuvem\AtelieAdmin\Model\ResourceModel\Production;
use Nuvem\AtelieAdmin\Model\Source\ProductionStatus;
use Psr\Log\LoggerInterface;

class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::production';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly Validator $formKeyValidator,
        private readonly Production $resource,
        private readonly ProductionStatus $statuses,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $status = $this->getRequest()->getParam('status');
        if (!$this->formKeyValidator->validate($this->getRequest()) || !is_string($status)
            || !$this->statuses->isValid($status)
        ) {
            $this->messageManager->addErrorMessage(__('Sua sessão expirou ou o status é inválido.'));
            return $redirect;
        }
        try {
            $collection = $this->collectionFactory->getReport('nuvem_atelie_production_listing_data_source');
            $ids = $this->filter->getCollection($collection)->getAllIds();
            if (!$ids) {
                throw new LocalizedException(__('Selecione pelo menos uma ficha.'));
            }
            $count = $this->resource->updateStatuses(array_map('intval', $ids), $status);
            $this->messageManager->addSuccessMessage(__('%1 ficha(s) atualizada(s).', $count));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Throwable $exception) {
            $this->logger->error('Atelie: production mass status update failed.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(__('Não foi possível atualizar as fichas.'));
        }
        return $redirect;
    }
}
