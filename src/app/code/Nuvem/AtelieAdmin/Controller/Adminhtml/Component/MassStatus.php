<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Component;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Nuvem\Atelie\Model\ResourceModel\Component\CollectionFactory;
use Nuvem\AtelieAdmin\Model\Component\GridUpdater;
use Psr\Log\LoggerInterface;

class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::components_edit';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly Validator $formKeyValidator,
        private readonly GridUpdater $updater,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/index');
        $status = $this->getRequest()->getParam('ativo');
        if (!$this->formKeyValidator->validate($this->getRequest()) || !is_scalar($status)
            || !in_array((string) $status, ['0', '1'], true)
        ) {
            $this->messageManager->addErrorMessage(__('Sua sessão expirou ou o status é inválido.'));
            return $redirect;
        }
        $count = 0;
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            foreach ($collection->getAllIds() as $id) {
                $this->updater->update((int) $id, ['ativo' => (int) $status]);
                $count++;
            }
            $this->messageManager->addSuccessMessage(__('%1 componente(s) atualizado(s).', $count));
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                __('%1 componente(s) atualizado(s). %2', $count, $exception->getMessage())
            );
        } catch (\Throwable $exception) {
            $this->logger->error('Atelie: mass status update failed.', ['exception' => $exception]);
            $this->messageManager->addErrorMessage(
                __('%1 componente(s) atualizado(s). Não foi possível concluir a operação.', $count)
            );
        }
        return $redirect;
    }
}
