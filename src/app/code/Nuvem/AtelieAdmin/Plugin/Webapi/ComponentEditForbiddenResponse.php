<?php

declare(strict_types=1);

namespace Nuvem\AtelieAdmin\Plugin\Webapi;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Phrase;
use Magento\Framework\Webapi\ErrorProcessor;
use Magento\Framework\Webapi\Exception as WebapiException;

class ComponentEditForbiddenResponse
{
    public function __construct(
        private readonly UserContextInterface $userContext
    ) {
    }

    public function afterMaskException(
        ErrorProcessor $subject,
        WebapiException $result,
        \Exception $exception
    ): WebapiException {
        if (!$exception instanceof AuthorizationException
            || $result->getHttpCode() !== WebapiException::HTTP_UNAUTHORIZED
            || $this->userContext->getUserType() !== UserContextInterface::USER_TYPE_ADMIN
            || !$this->userContext->getUserId()
        ) {
            return $result;
        }

        $resource = $exception->getParameters()['resources'] ?? '';

        if ($resource !== 'Nuvem_AtelieAdmin::components_edit') {
            return $result;
        }

        return new WebapiException(
            new Phrase($result->getRawMessage(), $result->getParameters()),
            (int) $result->getCode(),
            WebapiException::HTTP_FORBIDDEN,
            $result->getDetails(),
            $result->getName(),
            $result->getErrors(),
            $result->getStackTrace()
        );
    }
}
