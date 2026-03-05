<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Context;
use Magento\Backend\Model\Auth\Session;
use Magento\Config\Block\System\Config\Form\Fieldset;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Filesystem\Directory\ReadFactory;
use Magento\Framework\View\Helper\Js;

class ModuleInfo extends Fieldset
{
    private const MODULE_NAME = 'Rollpix_QrLanding';
    private const REPO_URL = 'https://github.com/ROLLPIX/M2-QR-Landing';

    public function __construct(
        Context $context,
        Session $authSession,
        Js $jsHelper,
        private readonly ComponentRegistrar $componentRegistrar,
        private readonly ReadFactory $readFactory,
        array $data = []
    ) {
        parent::__construct($context, $authSession, $jsHelper, $data);
    }

    public function render(AbstractElement $element): string
    {
        $html = $this->_getHeaderHtml($element);
        $html .= $this->getModuleInfoHtml();
        $html .= $this->_getFooterHtml($element);
        return $html;
    }

    private function getModuleInfoHtml(): string
    {
        $version = $this->getModuleVersion();
        $repoUrl = self::REPO_URL;

        $html = '<tr><td colspan="4" style="padding: 10px 15px;">';
        $html .= '<table style="width: auto; border-collapse: collapse;">';

        $html .= '<tr>';
        $html .= '<td style="padding: 5px 20px 5px 0; font-weight: 600;">' . $this->escapeHtml(__('Module')) . '</td>';
        $html .= '<td style="padding: 5px 0;">' . $this->escapeHtml(self::MODULE_NAME) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="padding: 5px 20px 5px 0; font-weight: 600;">' . $this->escapeHtml(__('Version')) . '</td>';
        $html .= '<td style="padding: 5px 0;">' . $this->escapeHtml($version) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="padding: 5px 20px 5px 0; font-weight: 600;">' . $this->escapeHtml(__('Repository')) . '</td>';
        $html .= '<td style="padding: 5px 0;"><a href="' . $this->escapeUrl($repoUrl) . '" target="_blank" rel="noopener">'
            . $this->escapeHtml($repoUrl) . '</a></td>';
        $html .= '</tr>';

        $html .= '</table>';
        $html .= '</td></tr>';

        return $html;
    }

    private function getModuleVersion(): string
    {
        try {
            $path = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, self::MODULE_NAME);
            $directoryRead = $this->readFactory->create($path);
            $composerJsonData = json_decode($directoryRead->readFile('composer.json'), true);
            return $composerJsonData['version'] ?? (string) __('Unknown');
        } catch (\Exception $e) {
            return (string) __('Unknown');
        }
    }
}
