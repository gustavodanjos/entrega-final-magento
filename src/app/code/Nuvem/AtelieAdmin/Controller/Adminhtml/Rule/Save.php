<?php
declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Controller\Adminhtml\Rule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Nuvem\Atelie\Api\RuleRepositoryInterface;
use Nuvem\Atelie\Model\RuleFactory;
use Nuvem\AtelieAdmin\Model\Rule\FormValidator;
use Psr\Log\LoggerInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Nuvem_AtelieAdmin::rules';

    public function __construct(
        Context $context,
        private readonly RuleRepositoryInterface $ruleRepository,
        private readonly RuleFactory $ruleFactory,
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
        $input = is_array($post) ? ($post['data'] ?? $post) : null;

        if (!is_array($input)) {
            $this->messageManager->addErrorMessage(
                __('Os dados da regra possuem um formato inválido.')
            );

            return $redirect->setPath('*/*/index');
        }

        $ruleId = null;
        $rawId = $input['regra_id'] ?? '';

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
                    __('O identificador da regra é inválido.')
                );

                return $redirect->setPath('*/*/index');
            }

            $ruleId = (int) $validatedId;
        }

        try {
            $rule = $ruleId !== null
                ? $this->ruleRepository->getById($ruleId)
                : $this->ruleFactory->create();

            $data = $this->formValidator->validate($input, $ruleId);

            $rule->setCodigo($data['codigo']);
            $rule->setTipo($data['tipo']);
            $rule->setOrigemComponenteId($data['origem_componente_id']);
            $rule->setAlvoTipo($data['alvo_tipo']);
            $rule->setAlvo($data['alvo']);
            $rule->setMensagem($data['mensagem']);
            $rule->setAtivo($data['ativo']);

            $rule = $this->ruleRepository->save($rule);

            $this->dataPersistor->clear('nuvem_atelie_rule');

            $this->messageManager->addSuccessMessage(
                __('A regra foi salva.')
            );

            return $redirect->setPath(
                '*/*/edit',
                ['regra_id' => $rule->getRegraId()]
            );
        } catch (NoSuchEntityException $exception) {
            $this->messageManager->addErrorMessage(
                __('A regra informada não existe mais.')
            );

            return $redirect->setPath('*/*/index');
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage(
                $exception->getMessage()
            );
        } catch (\Exception $exception) {
            $this->logger->critical($exception);

            $this->messageManager->addErrorMessage(
                __('Não foi possível salvar a regra.')
            );
        }

        $persisted = [];

        foreach ([
            'codigo',
            'tipo',
            'origem_componente_id',
            'alvo_tipo',
            'alvo_componente',
            'alvo_acabamento',
            'mensagem',
            'ativo',
        ] as $field) {
            $value = $input[$field] ?? '';

            $persisted[$field] = is_scalar($value)
                ? (string) $value
                : '';
        }

        if ($ruleId !== null) {
            $persisted['regra_id'] = $ruleId;
        }

        $this->dataPersistor->set(
            'nuvem_atelie_rule',
            $persisted
        );

        return $redirect->setPath(
            '*/*/edit',
            $ruleId !== null ? ['regra_id' => $ruleId] : []
        );
    }
}