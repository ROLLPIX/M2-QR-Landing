<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\DB\Sql\Expression;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Rollpix\QrLanding\Api\VisitsInterface;
use Rollpix\QrLanding\Model\ResourceModel\Visit as VisitResource;

class Visits implements VisitsInterface
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
    public function getVisits(
        ?string $from = null,
        ?string $to = null,
        ?string $sku = null,
        int $page = 1,
        int $pageSize = 50
    ): array {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $connection = $this->visitResource->getConnection();
        $tableName = $this->visitResource->getMainTable();

        $select = $connection->select()
            ->from($tableName, [
                'entity_id',
                'sku',
                'visitor_ip',
                'user_agent',
                'referer',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'created_at',
            ])
            ->where('store_id = ?', $storeId)
            ->order('created_at DESC');

        if ($from !== null && $from !== '') {
            $select->where('created_at >= ?', $from . ' 00:00:00');
        }
        if ($to !== null && $to !== '') {
            $select->where('created_at <= ?', $to . ' 23:59:59');
        }
        if ($sku !== null && $sku !== '') {
            $select->where('sku = ?', $sku);
        }

        // Total count
        $countSelect = $connection->select()
            ->from(['sub' => $select], [
                'total' => new Expression('COUNT(*)'),
            ]);
        $totalCount = (int) $connection->fetchOne($countSelect);

        // Paginate
        $page = max(1, $page);
        $pageSize = max(1, min($pageSize, 500));
        $offset = ($page - 1) * $pageSize;
        $select->limit($pageSize, $offset);

        $rows = $connection->fetchAll($select);

        // Cache product names to avoid repeated lookups
        $nameCache = [];
        $items = [];
        foreach ($rows as $row) {
            $rowSku = $row['sku'];
            if (!isset($nameCache[$rowSku])) {
                $nameCache[$rowSku] = $this->getProductName($rowSku, $storeId);
            }
            $items[] = [
                'entity_id' => (int) $row['entity_id'],
                'sku' => $rowSku,
                'product_name' => $nameCache[$rowSku],
                'visitor_ip' => $row['visitor_ip'],
                'user_agent' => $row['user_agent'],
                'referer' => $row['referer'] ?? '',
                'utm_source' => $row['utm_source'] ?? '',
                'utm_medium' => $row['utm_medium'] ?? '',
                'utm_campaign' => $row['utm_campaign'] ?? '',
                'created_at' => $row['created_at'],
            ];
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
        ];
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
