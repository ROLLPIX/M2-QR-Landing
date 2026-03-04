<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class IdentifierType implements OptionSourceInterface
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'sku', 'label' => __('SKU')],
        ];
    }
}
