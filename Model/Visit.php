<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Model;

use Magento\Framework\Model\AbstractModel;
use Rollpix\QrLanding\Model\ResourceModel\Visit as VisitResource;

class Visit extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(VisitResource::class);
    }
}
