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

    public function __construct(
        private readonly RequestInterface $request,
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly VisitFactory $visitFactory,
        private readonly VisitResource $visitResource,
        private readonly ForwardFactory $forwardFactory,
        private readonly RedirectFactory $redirectFactory,
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
            return $this->forwardFactory->create()->forward('noroute');
        }

        // Verify product is visible in current store
        if ((int) $product->getStatus() !== \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        // Track visit if enabled
        $shouldTrack = $this->request->getParam('track') === 'true';
        if ($shouldTrack && $this->config->isTrackingEnabled()) {
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
            $visit = $this->visitFactory->create();
            $visit->setData([
                'store_id' => $storeId,
                'sku' => $sku,
                'product_id' => $productId,
                'visitor_ip' => $this->request->getServer('REMOTE_ADDR', ''),
                'user_agent' => $this->request->getServer('HTTP_USER_AGENT', ''),
                'referer' => $this->request->getServer('HTTP_REFERER', ''),
                'utm_source' => $this->request->getParam('utm_source', ''),
                'utm_medium' => $this->request->getParam('utm_medium', ''),
                'utm_campaign' => $this->request->getParam('utm_campaign', ''),
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
