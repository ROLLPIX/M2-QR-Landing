<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Model;

use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Rollpix\QrLanding\Api\StatusInterface;
use Rollpix\QrLanding\Helper\Config;

class Status implements StatusInterface
{
    private const MODULE_NAME = 'Rollpix_QrLanding';

    public function __construct(
        private readonly Config $config,
        private readonly ComponentRegistrar $componentRegistrar,
        private readonly ReadFactory $readFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): array
    {
        return [
            'enabled' => $this->config->isEnabled(),
            'tracking_enabled' => $this->config->isTrackingEnabled(),
            'module_version' => $this->getModuleVersion(),
            'identifier_type' => $this->config->getIdentifierType(),
        ];
    }

    private function getModuleVersion(): string
    {
        try {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);
            $directoryRead = $this->readFactory->create($path);
            $composerJsonData = json_decode($directoryRead->readFile('composer.json'), true);
            return $composerJsonData['version'] ?? 'unknown';
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
}
