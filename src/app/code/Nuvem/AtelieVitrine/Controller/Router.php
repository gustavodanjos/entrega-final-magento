<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Controller;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Framework\App\Action\Forward;

class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        $path = trim($request->getPathInfo(), '/');

        if (!preg_match(
            '#^atelie/c/([a-f0-9]{32})/?$#',
            $path,
            $matches
        )) {
            return null;
        }

        $request->setModuleName('atelie');
        $request->setControllerName('creation');
        $request->setActionName('open');
        $request->setParam('hash', $matches[1]);

        return $this->actionFactory->create(Forward::class);
    }
}
