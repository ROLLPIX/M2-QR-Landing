<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'rollpix_qrlanding/general/enabled';
    private const XML_PATH_TRACKING_ENABLED = 'rollpix_qrlanding/general/tracking_enabled';
    private const XML_PATH_IDENTIFIER_TYPE = 'rollpix_qrlanding/general/identifier_type';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function isTrackingEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_TRACKING_ENABLED,
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getIdentifierType(): string
    {
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_IDENTIFIER_TYPE,
            ScopeInterface::SCOPE_STORE
        );
    }
}
