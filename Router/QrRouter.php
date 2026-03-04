<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Router;

use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

/**
 * Custom router to handle /qr/{sku} URLs.
 *
 * Rewrites /qr/ABC123 → qr/qr/index with param identifier=ABC123
 * so the standard router dispatches to Controller\Qr\Index.
 */
class QrRouter implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {
    }

    public function match(RequestInterface $request): ?\Magento\Framework\App\ActionInterface
    {
        $pathInfo = trim($request->getPathInfo(), '/');

        // Match /qr/{identifier} where identifier is non-empty
        if (!preg_match('#^qr/([^/]+)$#i', $pathInfo, $matches)) {
            return null;
        }

        $identifier = $matches[1];

        // Skip if it looks like a standard Magento controller path (qr/qr/index)
        if (strtolower($identifier) === 'qr') {
            return null;
        }

        $request->setModuleName('qr');
        $request->setControllerName('qr');
        $request->setActionName('index');
        $request->setParam('identifier', $identifier);

        return $this->actionFactory->create(\Magento\Framework\App\Action\Forward::class);
    }
}
