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
    use CategoryHelper;

    public function createVisibleTestProduct(array $overrides = [], bool $withVariant = false): ?ProductEntity
    {
        return $this->createTestProduct(array_merge($this->getBasicVisibilityData(), $overrides), $withVariant);
    }

    public function createVisibleTestProductWithCustomVariants(
        array $overrides = [],
        array $variants = []
    ): ?ProductEntity {
        $mainProduct = $this->buildProductInfo(
            array_merge($this->getBasicVisibilityData(), $overrides)
        );
        $products = [$mainProduct];

        foreach ($variants as $variant) {
            $variant['parentId'] = $mainProduct['id'];
            $products[] = $this->buildProductInfo(
                array_merge($this->getBasicVisibilityData(), $variant)
            );
        }

        return $this->upsertProducts($products);
    }

    public function createTestProduct(
        array $overrideData = [],
        bool $withVariant = false,
        bool $overrideRecursively = false,
        bool $withManufacturer = true
    ): ?ProductEntity {
        $mainProduct = $this->buildProductInfo($overrideData, $overrideRecursively, $withManufacturer);
        $products = [$mainProduct];

        if ($withVariant) {
            $products[] = [
                'id' => 'a5a1c99e6fbf2316523151de9e1aad31',
                'productNumber' => 'FINDOLOGIC001.1',
                'ean' => 'FL0011',
                'manufacturerNumber' => 'MAN0011',
                'name' => 'FINDOLOGIC VARIANT 1',
                'stock' => 10,
                'active' => true,
                'parentId' => $mainProduct['id'],
                'tax' => ['name' => '9%', 'taxRate' => 9],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
            ];

            $products[] = [
                'id' => 'edc0f84ed1e20dedff0ce81c1838758a',
                'productNumber' => 'FINDOLOGIC001.2',
                'ean' => 'FL0012',
                'manufacturerNumber' => 'MAN0012',
                'name' => 'FINDOLOGIC VARIANT 2',
                'stock' => 7,
                'active' => true,
                'parentId' => $mainProduct['id'],
                'tax' => ['name' => '9%', 'taxRate' => 9],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 80, 'net' => 66, 'linked' => false]]
            ];
        }

        return $this->upsertProducts($products);
    }

    public function buildProductInfo(
        array $overrideData = [],
        bool $overrideRecursively = false,
        bool $withManufacturer = true
    ): array {
        $id = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $container = $this->getContainer();

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
        $productData = [];
        $productData = array_merge(
            $productData,
            $this->getNameValues($overrideData['name'] ?? 'FINDOLOGIC Product')
        );

        $productData = array_merge($productData, [
            'id' => $id,
            'productNumber' => 'FINDOLOGIC001',
            'stock' => 10,
            'ean' => 'FL001',
            'description' => 'FINDOLOGIC Description',
            'tags' => [
                ['id' => Uuid::randomHex(), 'name' => 'FINDOLOGIC Tag']
            ],
            'cover' => $this->getDefaultCoverData(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(),  'name' => '9%', 'taxRate' => 9],
            'categories' => $this->getDefaultCategories(),
            'seoUrls' => $productSeoUrls,
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
        ]);

        if ($withManufacturer) {
            $productData = array_merge($productData, [
                'manufacturerNumber' => 'MAN001',
                'manufacturer' => ['name' => 'FINDOLOGIC'],
            ]);
        }

        return $overrideRecursively
            ? array_replace_recursive($productData, $overrideData)
            : array_merge($productData, $overrideData);
    }



    public function upsertProducts(array $productInfo): ?ProductEntity
    {
        $context = Context::createDefaultContext();
        $this->getContainer()->get('product.repository')->upsert($productInfo, $context);

        $id = current($productInfo)['id'];
        try {
            $criteria = new Criteria([$id]);
            $criteria = Utils::addProductAssociations($criteria);
            $criteria = Utils::addChildrenAssociations($criteria);
            $criteria->addAssociation('visibilities');

            return $this->getContainer()->get('product.repository')->search($criteria, $context)->first();
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

    public function getNameValues(string $name): array
    {
        return [
            'name' => $name,
            'translations' => [
                'de-DE' => [
                    'name' => $name . ' DE',
                ],
                'en-GB' => [
                    'name' => $name . ' EN',
                ],
            ],
        ];
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

    public function getBasicVisibilityData(): array
    {
        return [
            'visibilities' => [
                [
                    'id' => Uuid::randomHex(),
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                    'visibility' => 20
                ]
            ]
        ];
    }

    public function getDefaultCoverData(): array
    {
        return [
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
        ];
    }

    public function getDefaultCategories(): array
    {
        $categoryId = Uuid::randomHex();
        $newCategoryId = Uuid::randomHex();
        $navigationCategoryId = $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $this->createTestCategory([
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
        ]);

        return [
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
        ];
    }
}
