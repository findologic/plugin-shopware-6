<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

trait ProductHelper
{
    public function createTestProduct(array $data = []): ?ProductEntity
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $blueId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $productData = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'ean' => Uuid::randomHex(),
            'name' => 'Test name',
            'manufacturerNumber' => Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'FINDOLOGIC'],
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'categories' => [
                ['id' => $id, 'name' => 'Test Category'],
            ],
            'translations' => [
                'en-GB' => [
                    'customTranslated' => [
                        'root' => 'test',
                    ],
                ],
                'de-DE' => [
                    'customTranslated' => null,
                ],
            ],
            'media' => [
                ['id' => Uuid::randomHex(), 'position' => 4, 'media' => ['fileName' => 'd']],
                ['id' => Uuid::randomHex(), 'position' => 2, 'media' => ['fileName' => 'b']],
                ['id' => Uuid::randomHex(), 'position' => 1, 'media' => ['fileName' => 'a']],
                ['id' => Uuid::randomHex(), 'position' => 3, 'media' => ['fileName' => 'c']],
            ],
            'properties' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => 'color'],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
            'options' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => $colorId],
                ],
                [
                    'id' => $blueId,
                    'name' => 'blue',
                    'groupId' => $colorId,
                ],
            ],
            'configuratorSettings' => [
                [
                    'id' => $redId,
                    'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false],
                    'option' => [
                        'id' => $redId,
                        'name' => 'red',
                        'group' => ['id' => $colorId, 'name' => $colorId],
                    ],
                ],
                [
                    'id' => $blueId,
                    'price' => ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 90, 'linked' => false],
                    'option' => [
                        'id' => $blueId,
                        'name' => 'blue',
                        'groupId' => $colorId,
                    ],
                ],
            ],
        ];

        $productData = array_merge($data, $productData);

        $this->getContainer()->get('product.repository')->upsert([$productData], $context);

        try {
            $criteria = new Criteria([$id]);
            $criteria = Utils::addProductAssociations($criteria);

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
