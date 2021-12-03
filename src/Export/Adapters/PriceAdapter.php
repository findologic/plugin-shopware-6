<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Price;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;

class PriceAdapter
{
    /** @var ExportContext */
    private $exportContext;

    public function __construct(ExportContext $exportContext)
    {
        $this->exportContext = $exportContext;
    }

    /**
     * @return Price[]
     * @throws ProductHasNoPricesException
     */
    public function adapt(ProductEntity $product): array
    {
        $prices = $this->getPricesFromProduct($product);
        if (Utils::isEmpty($prices)) {
            throw new ProductHasNoPricesException($product);
        }

        return $prices;
    }

    /**
     * @return Price[]
     */
    protected function getPricesFromProduct(ProductEntity $product): array
    {
        $prices = [];

        foreach ($product->getPrice() as $item) {
            foreach ($this->exportContext->getCustomerGroups() as $customerGroup) {
                $userGroupHash = Utils::calculateUserGroupHash($this->exportContext->getShopkey(), $customerGroup->getId());
                if (Utils::isEmpty($userGroupHash)) {
                    continue;
                }

                $price = new Price();
                if ($customerGroup->getDisplayGross()) {
                    $price->setValue($item->getGross(), $userGroupHash);
                } else {
                    $price->setValue($item->getNet(), $userGroupHash);
                }

                $prices[] = $price;
            }

            $price = new Price();
            $price->setValue($item->getGross());
            $prices[] = $price;
        }

        return $prices;
    }
}
