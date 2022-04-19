<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Debug;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DebugProductSearch
{
    /** @var ContainerInterface */
    private $container;

    /** @var SalesChannelContext|null */
    private $salesChannelContext;

    public function __construct(
        ContainerInterface $container,
        SalesChannelContext $salesChannelContext
    ) {

        $this->container = $container;
        $this->salesChannelContext = $salesChannelContext;
    }

    public function fetchProductResult(string $productId, ?bool $withVariantInformation = false): EntitySearchResult
    {
        $criteria = $this->buildCriteria($productId, true, $withVariantInformation);

        /** @var EntitySearchResult $entityResult */
        return $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        );
    }

    public function searchProduct(Criteria $criteria): ?ProductEntity
    {
        return $this->container->get('product.repository')->search(
            $criteria,
            $this->salesChannelContext->getContext()
        )->first();
    }

    public function buildCriteria(
        string $productId,
        ?bool $withAssociations = true,
        ?bool $withVariantInformation = false
    ): Criteria {
        $criteria = new Criteria();

        $multiFilter = new MultiFilter(MultiFilter::CONNECTION_OR);
        $multiFilter->addQuery(
            new EqualsFilter('id', $productId)
        );

        if ($withVariantInformation) {
            $multiFilter->addQuery(
                new EqualsFilter('parentId', $productId)
            );
        }

        $criteria->addFilter($multiFilter);

        if ($withAssociations) {
            Utils::addProductAssociations($criteria);
        }

        return $criteria;
    }
}
