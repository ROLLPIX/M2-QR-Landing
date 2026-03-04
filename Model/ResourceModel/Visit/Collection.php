<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Model\ResourceModel\Visit;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Rollpix\QrLanding\Model\Visit;
use Rollpix\QrLanding\Model\ResourceModel\Visit as VisitResource;

class Collection extends AbstractCollection
{
    protected function _construct(): void
    {
        $this->_init(Visit::class, VisitResource::class);
    }
}
