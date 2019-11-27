<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

trait ProductHelperTrait
{
    public function createTestProduct(array $data = []): ?ProductEntity
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $productData = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'ean' => Uuid::randomHex(),
            'description' => 'some long description text',
            'tags' => [
                ['id' => $id, 'name' => 'Findologic Tag']
            ],
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
            'properties' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => 'color'],
                ]
            ],
            'options' => [
                [
                    'id' => $redId,
                    'name' => 'red',
                    'group' => ['id' => $colorId, 'name' => $colorId],
                ]
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
                ]
            ],
        ];

        $productData = array_merge($data, $productData);

        $container->get('product.repository')->upsert([$productData], $context);

        try {
            $criteria = new Criteria([$id]);
            $criteria = Utils::addProductAssociations($criteria);

            /** @var ProductEntity $product */
            $productEntity = $container->get('product.repository')->search($criteria, $context)->get($id);

            return $productEntity;
        } catch (InconsistentCriteriaIdsException $e) {
            return null;
        }
    }
}
