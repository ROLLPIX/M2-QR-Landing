<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Controller\Qr;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Rollpix\QrLanding\Helper\Config;
use Rollpix\QrLanding\Model\ResourceModel\Visit as VisitResource;
use Rollpix\QrLanding\Model\VisitFactory;

class Index implements HttpGetActionInterface
{
    private const TRACKING_FIELDS = ['source', 'medium', 'campaign', 'term', 'content'];

    private const UTM_PREFIX = 'utm_';

    /**
     * Magento's Varnish VCL strips utm_* (and gclid, fbclid...) from the URL before it reaches PHP, so a
     * redirect built from them loses the campaign. qr_* survive the VCL and are re-emitted as utm_*.
     */
    private const ALIAS_PREFIX = 'qr_';

    private const DEDUP_WINDOW_MINUTES = 5;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly VisitFactory $visitFactory,
        private readonly VisitResource $visitResource,
        private readonly ForwardFactory $forwardFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly RemoteAddress $remoteAddress,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): \Magento\Framework\Controller\ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $identifier = (string) $this->request->getParam('identifier', '');
        if ($identifier === '') {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $store = $this->storeManager->getStore();
        $storeId = (int) $store->getId();

        try {
            $product = $this->productRepository->get($identifier, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return $this->handleNotFound();
        }

        if (!$this->canShowInStore($product, (int) $store->getWebsiteId())) {
            return $this->handleNotFound();
        }

        $tracking = $this->resolveTrackingParams();

        // Track visit if tracking is enabled in config
        if ($this->config->isTrackingEnabled()) {
            $this->recordVisit($storeId, $identifier, (int) $product->getId(), $tracking);
        }

        // Build redirect URL with UTM params
        $productUrl = $product->getProductUrl();
        if (!empty($tracking)) {
            $separator = (parse_url($productUrl, PHP_URL_QUERY) !== null) ? '&' : '?';
            $productUrl .= $separator . http_build_query($tracking);
        }

        $redirect = $this->redirectFactory->create();
        $redirect->setUrl($productUrl);
        $redirect->setHttpResponseCode(302);

        return $redirect;
    }

    /**
     * Enabled is not enough: a product outside this website, or not visible individually, has no product
     * page here, and redirecting to it would end in a 404.
     */
    private function canShowInStore(ProductInterface $product, int $websiteId): bool
    {
        if ((int) $product->getStatus() !== \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED) {
            return false;
        }

        if ($product instanceof Product && !$product->isVisibleInSiteVisibility()) {
            return false;
        }

        $websiteIds = array_map('intval', (array) $product->getWebsiteIds());

        return in_array($websiteId, $websiteIds, true);
    }

    /**
     * @param array<string, string> $tracking utm_* => value
     */
    private function recordVisit(int $storeId, string $sku, int $productId, array $tracking): void
    {
        try {
            $visitorIp = (string) $this->remoteAddress->getRemoteAddress();

            // Deduplicate: skip if same IP+SKU visited within the last N minutes
            if ($this->isRecentVisit($storeId, $sku, $visitorIp)) {
                return;
            }

            $visit = $this->visitFactory->create();
            $visit->setData([
                'store_id' => $storeId,
                'sku' => $sku,
                'product_id' => $productId,
                'visitor_ip' => mb_substr($visitorIp, 0, 45),
                'user_agent' => mb_substr(strip_tags((string) $this->request->getServer('HTTP_USER_AGENT', '')), 0, 512),
                'referer' => mb_substr(strip_tags((string) $this->request->getServer('HTTP_REFERER', '')), 0, 512),
                'utm_source' => $this->sanitize($tracking['utm_source'] ?? ''),
                'utm_medium' => $this->sanitize($tracking['utm_medium'] ?? ''),
                'utm_campaign' => $this->sanitize($tracking['utm_campaign'] ?? ''),
            ]);
            $this->visitResource->save($visit);
        } catch (\Exception $e) {
            // Never break the redirect flow due to tracking failure
            $this->logger->error('Rollpix QR Landing: Failed to record visit', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sanitize(string $value): string
    {
        return mb_substr(strip_tags($value), 0, 255);
    }

    private function isRecentVisit(int $storeId, string $sku, string $ip): bool
    {
        $connection = $this->visitResource->getConnection();
        $tableName = $this->visitResource->getMainTable();
        $threshold = date('Y-m-d H:i:s', time() - self::DEDUP_WINDOW_MINUTES * 60);

        $select = $connection->select()
            ->from($tableName, ['entity_id'])
            ->where('store_id = ?', $storeId)
            ->where('sku = ?', $sku)
            ->where('visitor_ip = ?', $ip)
            ->where('created_at >= ?', $threshold)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    private function handleNotFound(): \Magento\Framework\Controller\ResultInterface
    {
        $fallbackUrl = $this->config->getFallbackUrl();
        if ($fallbackUrl !== '') {
            $redirect = $this->redirectFactory->create();
            $redirect->setUrl($fallbackUrl);
            $redirect->setHttpResponseCode(302);
            return $redirect;
        }
        return $this->forwardFactory->create()->forward('noroute');
    }

    /**
     * Each tracking field from utm_* when it arrives, otherwise from its qr_* alias. Always keyed as utm_*:
     * that is what gets stored and what the product page URL carries for analytics.
     *
     * @return array<string, string>
     */
    private function resolveTrackingParams(): array
    {
        $params = [];
        foreach (self::TRACKING_FIELDS as $field) {
            $value = (string) $this->request->getParam(self::UTM_PREFIX . $field, '');
            if ($value === '') {
                $value = (string) $this->request->getParam(self::ALIAS_PREFIX . $field, '');
            }
            if ($value !== '') {
                $params[self::UTM_PREFIX . $field] = $value;
            }
        }
        return $params;
    }
}
