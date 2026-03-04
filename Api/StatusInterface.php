<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Api;

interface StatusInterface
{
    /**
     * Get QR Landing module status for current store view
     *
     * @return mixed[]
     */
    public function getStatus(): array;
}
