<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

trait ProductHelper
{
    public function createVisibleTestProduct(array $overrides = [], bool $withVariant = false): ?ProductEntity
    {
        return $this->createTestProduct(array_merge([
            'visibilities' => [
                [
                    'id' => Uuid::randomHex(),
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => 20
                ]
            ]
        ], $overrides), $withVariant);
    }

    public function createTestProduct(
        array $overrideData = [],
        bool $withVariant = false,
        bool $overrideRecursively = false
    ): ?ProductEntity {
        $context = Context::createDefaultContext();
        $id = '29d554327a16fd51350688cfa9930b29';
        $categoryId = Uuid::randomHex();
        $newCategoryId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $container = $this->getContainer();
        $navigationCategoryId = $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId();
        $categoryData = [
            [
                'id' => Uuid::randomHex(),
                'name' => 'FINDOLOGIC Main 2',
                'children' => [
                    [
                        'id' => $newCategoryId,
                        'name' => 'FINDOLOGIC Sub',
                        'children' => [
                            [
                                'id' => Uuid::randomHex(),
                                'name' => 'Very deep'
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $container->get('category.repository')->upsert($categoryData, $context);
        $seoUrlRepo = $container->get('seo_url.repository');
        $seoUrls = $seoUrlRepo->search(new Criteria(), Context::createDefaultContext());
        $seoUrlsStoreFrontContext = $seoUrlRepo->search(new Criteria(), $this->salesChannelContext->getContext());

        $productSeoUrls = [];
        if ($seoUrls->count() === 0 && $seoUrlsStoreFrontContext->count() === 0) {
            $productSeoUrls = [
                [
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'Awesome-Seo-Url/&ecause/SÄÖ/is/$mportant+',
                    'isCanonical' => true,
                    'routeName' => 'frontend.detail.page'
                ],
            ];
        }

        $productData = [
            'id' => $id,
            'productNumber' => 'FINDOLOGIC001',
            'stock' => 10,
            'ean' => 'FL001',
            'description' => 'FINDOLOGIC Description',
            'tags' => [
                ['id' => Uuid::randomHex(), 'name' => 'FINDOLOGIC Tag']
            ],
            'name' => 'FINDOLOGIC Product',
            'cover' => [
                'media' => [
                    'url' => 'https://via.placeholder.com/1000',
                    'private' => false,
                    'mediaType' => 'image/png',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => 'findologic',
                    'thumbnails' => [
                        [
                            'width' => 600,
                            'height' => 600,
                            'highDpi' => false,
                            'url' => 'https://via.placeholder.com/600'
                        ]
                    ]
                ],
            ],
            'manufacturerNumber' => 'MAN001',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'FINDOLOGIC'],
            'tax' => ['id' => Uuid::randomHex(),  'name' => '9%', 'taxRate' => 9],
            'categories' => [
                [
                    'parentId' => $navigationCategoryId ?: null,
                    'id' => $categoryId,
                    'name' => 'FINDOLOGIC Category',
                    'seoUrls' => [
                        [
                            'pathInfo' => 'navigation/' . $categoryId,
                            'seoPathInfo' => 'Findologic-Category',
                            'isCanonical' => true,
                            'routeName' => 'frontend.navigation.page'
                        ]
                    ]
                ],
                [
                    'parentId' => $newCategoryId,
                    'id' => Uuid::randomHex(),
                    'name' => 'FINDOLOGIC Sub of Sub'
                ]
            ],
            'seoUrls' => $productSeoUrls,
            'translations' => [
                'de-DE' => [
                    'name' => 'FINDOLOGIC Product DE',
                ],
                'en-GB' => [
                    'name' => 'FINDOLOGIC Product EN',
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

        $productInfo = [];
        // Main product data
        if ($overrideRecursively) {
            $productInfo[] = array_replace_recursive($productData, $overrideData);
        } else {
            $productInfo[] = array_merge($productData, $overrideData);
        }

        if ($withVariant) {
            // Standard variant data
            $variantData = [
                'id' => 'a5a1c99e6fbf2316523151de9e1aad31',
                'productNumber' => 'FINDOLOGIC001.1',
                'ean' => 'FL0011',
                'manufacturerNumber' => 'MAN0011',
                'name' => 'FINDOLOGIC VARIANT 1',
                'stock' => 10,
                'active' => true,
                'parentId' => $id,
                'tax' => ['name' => '9%', 'taxRate' => 9],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
            ];

            $productInfo[] = $variantData;

            $variantData2 = [
                'id' => 'edc0f84ed1e20dedff0ce81c1838758a',
                'productNumber' => 'FINDOLOGIC001.2',
                'ean' => 'FL0012',
                'manufacturerNumber' => 'MAN0012',
                'name' => 'FINDOLOGIC VARIANT 2',
                'stock' => 7,
                'active' => true,
                'parentId' => $id,
                'tax' => ['name' => '9%', 'taxRate' => 9],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 80, 'net' => 66, 'linked' => false]]
            ];

            $productInfo[] = $variantData2;
        }

        $container->get('product.repository')->upsert($productInfo, $context);

        try {
            $criteria = new Criteria([$id]);
            $criteria = Utils::addProductAssociations($criteria);
            $criteria->addAssociation('visibilities');

            return $container->get('product.repository')->search($criteria, $context)->get($id);
        } catch (InconsistentCriteriaIdsException $e) {
            return null;
        }
    }

    public function createProductReview(string $id, float $points, string $productId, bool $active): void
    {
        $customerId = Uuid::randomHex();
        $this->createCustomer($customerId);
        $salesChannelId = Defaults::SALES_CHANNEL;
        $languageId = Defaults::LANGUAGE_SYSTEM;
        $title = 'foo';

        $data = [
            'id' => $id,
            'productId' => $productId,
            'customerId' => $customerId,
            'salesChannelId' => $salesChannelId,
            'languageId' => $languageId,
            'status' => $active,
            'content' => 'this is a great product',
            'points' => $points,
            'title' => $title,
        ];

        $this->getContainer()->get('product_review.repository')->upsert([$data], Context::createDefaultContext());
    }

    public function createCustomer(string $customerId): void
    {
        $password = 'foo';
        $email = 'foo@bar.de';
        $addressId = Uuid::randomHex();

        $this->getContainer()->get('customer.repository')->upsert(
            [
                [
                    'id' => $customerId,
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'defaultShippingAddress' => [
                        'id' => $addressId,
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'street' => 'Musterstraße 1',
                        'city' => 'Schoöppingen',
                        'zipcode' => '12345',
                        'salutationId' => $this->getValidSalutationId(),
                        'country' => ['name' => 'Germany'],
                    ],
                    'defaultBillingAddressId' => $addressId,
                    'defaultPaymentMethod' => [
                        'name' => 'Invoice',
                        'description' => 'Default payment method',
                        'handlerIdentifier' => SyncTestPaymentHandler::class,
                    ],
                    'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                    'email' => $email,
                    'password' => $password,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'salutationId' => $this->getValidSalutationId(),
                    'customerNumber' => '12345',
                ],
            ],
            Context::createDefaultContext()
        );
    }

    public function getBasicVariantData(array $overrides = []): array
    {
        return array_merge([
            'stock' => 10,
            'active' => true,
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
        ], $overrides);
    }
}
