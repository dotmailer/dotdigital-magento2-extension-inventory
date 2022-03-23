<?php

namespace Dotdigitalgroup\Inventory\Model\Product;

use Dotdigitalgroup\Email\Api\StockFinderInterface;
use Magento\Catalog\Model\Product;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Dotdigitalgroup\Email\Logger\Logger;
use Dotdigitalgroup\Inventory\Model\Product\Stock\SalableQuantity;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Model\Configuration;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;

class StockFinder implements StockFinderInterface
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var SalableQuantity
     */
    private $salableQuantity;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * StockFinder constructor.
     *
     * @param Logger $logger
     * @param SalableQuantity $salableQuantity
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ScopeConfigInterface $scopeConfig
     * @param SourceItemRepositoryInterface $sourceItemRepository
     */
    public function __construct(
        Logger $logger,
        SalableQuantity $salableQuantity,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        ScopeConfigInterface $scopeConfig,
        SourceItemRepositoryInterface $sourceItemRepository
    ) {
        $this->logger = $logger;
        $this->salableQuantity = $salableQuantity;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->sourceItemRepository = $sourceItemRepository;
    }

    /**
     * Get product stock
     *
     * @param Product $product
     * @param int $websiteId
     * @return float|int
     */
    public function getStockQty($product, int $websiteId)
    {
        try {
            switch ($product->getTypeId()) {
                case 'configurable':
                    return $this->getStockQtyForConfigurableProduct($product, $websiteId);
                default:
                    return $this->getStockQtyForProducts([$product], $websiteId);
            }
        } catch (\Exception $e) {
            $this->logger->debug(
                'Stock qty not found for ' . $product->getTypeId() . ' product id ' . $product->getId(),
                [(string) $e]
            );
            return 0;
        }
    }

    /**
     * Get Stock for configurable products
     *
     * @param Product $configurableProduct
     * @param int $websiteId
     * @return float|int
     */
    private function getStockQtyForConfigurableProduct(ProductInterface $configurableProduct, int $websiteId)
    {
        $configurableProductInstance = $configurableProduct->getTypeInstance();
        /** @var Configurable $configurableProductInstance */
        $simpleProducts = $configurableProductInstance->getUsedProducts($configurableProduct);
        return $this->getStockQtyForProducts($simpleProducts, $websiteId);
    }

    /**
     * Calculate available stock for an array of products.
     *
     * If Manage Stock is disabled globally, or disabled for individual SKUs,
     * or if catalog sync is running at default level, we cannot use
     * salable quantity (stock is allocated to website sales channels).
     * In these cases we fall back to the 'Quantity per Source'.
     *
     * @param ProductInterface[] $products
     * @param int $websiteId
     * @return float|int
     */
    private function getStockQtyForProducts(array $products, int $websiteId)
    {
        $qty = 0;
        $skusWithNotManageStock = [];

        $manageStock = $this->scopeConfig->getValue(
            Configuration::XML_PATH_MANAGE_STOCK
        );

        if (!$manageStock || $websiteId === 0) {
            foreach ($products as $product) {
                $skusWithNotManageStock[] = $product->getSku();
            }
        } else {
            foreach ($products as $product) {
                try {
                    $qty += $this->salableQuantity->getSalableQuantity($product, $websiteId);
                } catch (\Exception $e) {
                    $skusWithNotManageStock[] = $product->getSku();
                    continue;
                }
            }
        }

        if ($skusWithNotManageStock) {
            $sourceItems = $this->loadInventorySourceItems($skusWithNotManageStock);
            foreach ($sourceItems as $item) {
                $qty += $item->getQuantity();
            }
        }

        return $qty;
    }

    /**
     * Set collection filter criteria
     *
     * @param array $skus
     * @return SourceItemInterface[]
     */
    private function loadInventorySourceItems(array $skus): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('sku', $skus, 'in')
            ->create();

        return $this->sourceItemRepository->getList($searchCriteria)->getItems();
    }
}
