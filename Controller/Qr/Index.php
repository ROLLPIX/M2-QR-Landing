<?php

declare(strict_types=1);

namespace Rollpix\QrLanding\Controller\Qr;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\App\ResponseInterface;
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
    private const UTM_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    ];

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

        $storeId = (int) $this->storeManager->getStore()->getId();

        try {
            $product = $this->productRepository->get($identifier, false, $storeId);
        } catch (NoSuchEntityException $e) {
            return $this->handleNotFound();
        }

        // Verify product is visible in current store
        if ((int) $product->getStatus() !== \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED) {
            return $this->handleNotFound();
        }

        // Track visit if tracking is enabled in config
        if ($this->config->isTrackingEnabled()) {
            $this->recordVisit($storeId, $identifier, (int) $product->getId());
        }

        // Build redirect URL with UTM params
        $productUrl = $product->getProductUrl();
        $utmParams = $this->extractUtmParams();

        if (!empty($utmParams)) {
            $separator = (parse_url($productUrl, PHP_URL_QUERY) !== null) ? '&' : '?';
            $productUrl .= $separator . http_build_query($utmParams);
        }

        $redirect = $this->redirectFactory->create();
        $redirect->setUrl($productUrl);
        $redirect->setHttpResponseCode(302);

        return $redirect;
    }

    private function recordVisit(int $storeId, string $sku, int $productId): void
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
                'utm_source' => mb_substr(strip_tags((string) $this->request->getParam('utm_source', '')), 0, 255),
                'utm_medium' => mb_substr(strip_tags((string) $this->request->getParam('utm_medium', '')), 0, 255),
                'utm_campaign' => mb_substr(strip_tags((string) $this->request->getParam('utm_campaign', '')), 0, 255),
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
     * @return array<string, string>
     */
    private function extractUtmParams(): array
    {
        $params = [];
        foreach (self::UTM_PARAMS as $param) {
            $value = (string) $this->request->getParam($param, '');
            if ($value !== '') {
                $params[$param] = $value;
            }
        }
        return $params;
    }
}
