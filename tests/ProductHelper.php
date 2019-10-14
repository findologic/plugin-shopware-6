<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

trait ProductHelper
{
    public function createTestProduct(array $data = []): ProductEntity
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

        $productData = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'name' => 'Test name',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'FINDOLOGIC'],
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'categories' => [
                ['id' => $id, 'name' => 'Test Category'],
            ],
        ];

        $productData = array_merge($data, $productData);

        $this->getContainer()->get('product.repository')->upsert([$productData], $context);

        try {
            $criteria = new Criteria([$id]);
            $criteria->addAssociation('categories');
            /** @var ProductEntity $product */
            $productEntity = $this->getContainer()
                ->get('product.repository')
                ->search($criteria, $context)
                ->get($id);

            return $productEntity;
        } catch (InconsistentCriteriaIdsException $e) {
            return null;
        }
    }
}
