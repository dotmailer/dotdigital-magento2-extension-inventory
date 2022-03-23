<?php

namespace Dotdigitalgroup\Inventory\Model\Product\Stock;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryConfigurationApi\Api\GetStockItemConfigurationInterface;
use Magento\InventorySalesAdminUi\Model\ResourceModel\GetAssignedStockIdsBySku;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Model\GetAssignedSalesChannelsForStockInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

class SalableQuantity
{
    /**
     * @var GetProductSalableQtyInterface
     */
    private $getProductSalableQty;

    /**
     * @var GetAssignedStockIdsBySku
     */
    private $getAssignedStockIdsBySku;

    /**
     * @var GetStockItemConfigurationInterface
     */
    private $getStockItemConfiguration;

    /**
     * @var GetAssignedSalesChannelsForStockInterface
     */
    private $getAssignedSalesChannelsForStock;

    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param GetAssignedStockIdsBySku $getAssignedStockIdsBySku
     * @param GetStockItemConfigurationInterface $getStockItemConfiguration
     * @param GetAssignedSalesChannelsForStockInterface $getAssignedSalesChannelsForStock
     * @param WebsiteRepositoryInterface $websiteRepository
     */
    public function __construct(
        GetProductSalableQtyInterface $getProductSalableQty,
        GetAssignedStockIdsBySku $getAssignedStockIdsBySku,
        GetStockItemConfigurationInterface $getStockItemConfiguration,
        GetAssignedSalesChannelsForStockInterface $getAssignedSalesChannelsForStock,
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->getProductSalableQty = $getProductSalableQty;
        $this->getAssignedStockIdsBySku = $getAssignedStockIdsBySku;
        $this->getStockItemConfiguration = $getStockItemConfiguration;
        $this->getAssignedSalesChannelsForStock = $getAssignedSalesChannelsForStock;
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * Get Salable Quantity
     *
     * @param ProductInterface $product
     * @param int $websiteId
     * @return float|int
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSalableQuantity(ProductInterface $product, int $websiteId)
    {
        $qty = 0;
        $stockIds = $this->getAssignedStockIdsBySku->execute($product->getSku());
        $stockIdsInScope = [];

        foreach ($stockIds as $stockId) {
            $stockItemConfiguration = $this->getStockItemConfiguration->execute($product->getSku(), $stockId);
            if (!$stockItemConfiguration->isManageStock()) {
                throw new LocalizedException(
                    __('Manage stock is turned off for this SKU.')
                );
            }

            if ($this->stockIdMatchesScope($stockId, $websiteId)) {
                $stockIdsInScope[] = $stockId;
            }
        }

        foreach ($stockIdsInScope as $stockId) {
            $qty += $this->getProductSalableQty->execute($product->getSku(), $stockId);
        }

        return $qty;
    }

    /**
     * Check Scope
     *
     * @param int $stockId
     * @param int $websiteId
     * @return bool
     * @throws NoSuchEntityException
     */
    private function stockIdMatchesScope(int $stockId, int $websiteId): bool
    {
        $salesChannels = $this->getAssignedSalesChannelsForStock->execute($stockId);
        foreach ($salesChannels as $channel) {
            if ($channel->getType() === SalesChannelInterface::TYPE_WEBSITE &&
                $channel->getCode() === $this->websiteRepository->getById($websiteId)->getCode()
            ) {
                return true;
            }
        }

        return false;
    }
}
