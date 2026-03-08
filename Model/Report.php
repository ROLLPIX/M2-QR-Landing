<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Rollpix\QrLanding\Api\ReportInterface;
use Rollpix\QrLanding\Model\ResourceModel\Visit as VisitResource;

class Report implements ReportInterface
{
    public function __construct(
        private readonly VisitResource $visitResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getReport(
        ?string $from = null,
        ?string $to = null,
        ?string $sku = null,
        int $page = 1,
        int $pageSize = 50
    ): array {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $connection = $this->visitResource->getConnection();
        $tableName = $this->visitResource->getMainTable();

        // --- Items: grouped by SKU with visit count and last visit ---
        $countSelect = $connection->select()
            ->from($tableName, [
                'sku',
                'visit_count' => new Expression('COUNT(*)'),
                'last_visit' => new Expression('MAX(created_at)'),
            ])
            ->where('store_id = ?', $storeId)
            ->group('sku');

        $this->applyFilters($countSelect, $from, $to, $sku);

        // Total count (number of distinct SKUs matching filters)
        $totalCountSelect = $connection->select()
            ->from(['sub' => $countSelect], [
                'total' => new Expression('COUNT(*)'),
            ]);
        $totalCount = (int) $connection->fetchOne($totalCountSelect);

        // Paginate
        $page = max(1, $page);
        $pageSize = max(1, min($pageSize, 500));
        $offset = ($page - 1) * $pageSize;
        $countSelect->limit($pageSize, $offset);
        $countSelect->order('visit_count DESC');

        $rows = $connection->fetchAll($countSelect);

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'sku' => $row['sku'],
                'product_name' => $this->getProductName($row['sku'], $storeId),
                'visit_count' => (int) $row['visit_count'],
                'last_visit' => $row['last_visit'],
            ];
        }

        // --- Daily: aggregated by date (with unique product count) ---
        $dailySelect = $connection->select()
            ->from($tableName, [
                'date' => new Expression('DATE(created_at)'),
                'count' => new Expression('COUNT(*)'),
                'unique_products' => new Expression('COUNT(DISTINCT sku)'),
            ])
            ->where('store_id = ?', $storeId)
            ->group(new Expression('DATE(created_at)'))
            ->order('date ASC');

        $this->applyFilters($dailySelect, $from, $to, $sku);

        $dailyRows = $connection->fetchAll($dailySelect);

        $daily = [];
        foreach ($dailyRows as $row) {
            $daily[] = [
                'date' => $row['date'],
                'count' => (int) $row['count'],
                'unique_products' => (int) $row['unique_products'],
            ];
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'daily' => $daily,
        ];
    }

    private function applyFilters(\Magento\Framework\DB\Select $select, ?string $from, ?string $to, ?string $sku): void
    {
        if ($from !== null && $from !== '') {
            $select->where('created_at >= ?', $from . ' 00:00:00');
        }
        if ($to !== null && $to !== '') {
            $select->where('created_at <= ?', $to . ' 23:59:59');
        }
        if ($sku !== null && $sku !== '') {
            $select->where('sku = ?', $sku);
        }
    }

    private function getProductName(string $sku, int $storeId): string
    {
        try {
            $product = $this->productRepository->get($sku, false, $storeId);
            return (string) $product->getName();
        } catch (NoSuchEntityException $e) {
            return '';
        }
    }
}
