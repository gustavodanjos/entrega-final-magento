<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Component;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\ComponentRepositoryInterface;
use Nuvem\Atelie\Model\ComponentFactory;
use Nuvem\AtelieAdmin\Model\Component\FormValidator;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::components_edit';

    public function __construct(
        Context $context,
        private readonly ComponentRepositoryInterface $componentRepository,
        private readonly ComponentFactory $componentFactory,
        private readonly FormValidator $formValidator,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $redirect = $this->resultRedirectFactory->create();

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            $this->messageManager->addErrorMessage(
                __('Sua sessão expirou. Reabra o formulário e tente novamente.')
            );

            return $redirect->setPath('*/*/index');
        }

        $post = $this->getRequest()->getPostValue();

        if (!is_array($post)) {
            $this->messageManager->addErrorMessage(
                __('Não foi possível ler os dados do componente.')
            );

            return $redirect->setPath('*/*/index');
        }

        $input = $post['data'] ?? $post;

        if (!is_array($input)) {
            $this->messageManager->addErrorMessage(
                __('Os dados do componente possuem um formato inválido.')
            );

            return $redirect->setPath('*/*/index');
        }

        $componentId = null;
        $rawId = $input['componente_id'] ?? '';

        if ($rawId !== '' && $rawId !== null) {
            $validatedId = is_scalar($rawId)
                ? filter_var(
                    $rawId,
                    FILTER_VALIDATE_INT,
                    ['options' => ['min_range' => 1]]
                )
                : false;

            if ($validatedId === false) {
                $this->messageManager->addErrorMessage(
                    __('O identificador do componente é inválido.')
                );

                return $redirect->setPath('*/*/index');
            }

            $componentId = (int) $validatedId;
        }

        try {
            if ($componentId !== null) {
                $component = $this->componentRepository->getById(
                    $componentId
                );
            } else {
                $component = $this->componentFactory->create();
                $component->setEstoque(0);
            }

            $data = $this->formValidator->validate(
                $input,
                $componentId
            );

            $component->setCodigo($data['codigo']);
            $component->setNome($data['nome']);
            $component->setGrupoId($data['grupo_id']);
            $component->setCorHex($data['cor_hex']);
            $component->setAcabamento($data['acabamento']);
            $component->setPrecoAdicional($data['preco_adicional']);
            $component->setOrdem($data['ordem']);
            $component->setAtivo($data['ativo']);
            $component->setPadrao($data['padrao']);

            $component = $this->componentRepository->save($component);

            $this->dataPersistor->clear('nuvem_atelie_component');

            $this->messageManager->addSuccessMessage(
                __('O componente foi salvo.')
            );

            return $redirect->setPath(
                '*/*/edit',
                ['componente_id' => $component->getComponenteId()]
            );
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(
                __('O componente informado não existe mais.')
            );

            return $redirect->setPath('*/*/index');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );
        } catch (\Exception $exception) {
            $this->logger->critical($exception);

            $this->messageManager->addErrorMessage(
                __('Não foi possível salvar o componente.')
            );
        }

        /*
         * Guarda somente os campos do formulário.
         * Não preserva form key nem outros parâmetros da requisição.
         */
        $persisted = [];

        foreach ([
            'codigo',
            'nome',
            'grupo_id',
            'cor_hex',
            'acabamento',
            'preco_adicional',
            'ordem',
            'ativo',
            'padrao',
        ] as $field) {
            $value = $input[$field] ?? '';

            $persisted[$field] = is_scalar($value)
                ? (string) $value
                : '';
        }

        if ($componentId !== null) {
            $persisted['componente_id'] = $componentId;
        }

        $this->dataPersistor->set(
            'nuvem_atelie_component',
            $persisted
        );

        return $redirect->setPath(
            '*/*/edit',
            $componentId !== null
                ? ['componente_id' => $componentId]
                : []
        );
    }
}