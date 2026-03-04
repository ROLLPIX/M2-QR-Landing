<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Visit extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('rollpix_qr_visits', 'entity_id');
    }
}
