<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Api;

interface ReportInterface
{
    /**
     * Get QR visit report for current store view
     *
     * @param string|null $from Date filter (Y-m-d)
     * @param string|null $to Date filter (Y-m-d)
     * @param string|null $sku Filter by specific SKU
     * @param int $page Page number (default 1)
     * @param int $pageSize Items per page (default 50)
     * @return mixed[]
     */
    public function getReport(
        ?string $from = null,
        ?string $to = null,
        ?string $sku = null,
        int $page = 1,
        int $pageSize = 50
    ): array;
}
