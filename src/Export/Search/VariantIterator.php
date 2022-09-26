<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Search;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Search\VariantIteratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Vin\ShopwareSdk\Data\Entity\Product\ProductCollection;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity;

class VariantIterator implements VariantIteratorInterface
{
    protected RepositoryIterator $repositoryIterator;

    public function __construct(
        EntityRepository $productRepository,
        Context $context,
        Criteria $criteria
    ) {
        $this->repositoryIterator = new RepositoryIterator(
            $productRepository,
            $context,
            $criteria,
        );
    }

    public function fetch(): ?ProductCollection
    {
        $products = $this->repositoryIterator
            ->fetch();

        if (!$products) {
            return null;
        }

        /** @var ProductCollection $sdkProducts */
        $sdkProducts = Utils::createSdkCollection(
            ProductCollection::class,
            ProductEntity::class,
            $products->getEntities()
        );

        return $sdkProducts;
    }
}
