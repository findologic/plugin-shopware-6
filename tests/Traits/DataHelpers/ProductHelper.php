<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Utils\Utils;
use FINDOLOGIC\Shopware6Common\Export\Constants;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Vin\ShopwareSdk\Data\Entity\Product\ProductEntity;

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

    public function createProductWithMultipleVariants(
        ?string $parentId = null,
        ?string $expectedFirstVariantId = null,
        ?string $expectedSecondVariantId = null,
        ?string $expectedThirdVariantId = null
    ): ?ProductEntity {
        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $thirdOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $parentId ??= Uuid::randomHex();

        $variants = [];
        $variants[] = $this->getBasicVariantData([
            'id' => $expectedFirstVariantId ?? Uuid::randomHex(),
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedSecondVariantId ?? Uuid::randomHex(),
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'options' => [
                ['id' => $secondOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedThirdVariantId ?? Uuid::randomHex(),
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.3',
            'name' => 'FINDOLOGIC VARIANT 3',
            'options' => [
                ['id' => $thirdOptionId]
            ],
        ]);

        return $this->createVisibleTestProductWithCustomVariants([
            'id' => $parentId,
            'active' => false,
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $firstOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $secondOptionId,
                        'name' => 'Orange',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $thirdOptionId,
                        'name' => 'Green',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ]
        ], $variants);
    }

    public function createProductWithDifferentPriceVariants(
        string $parentId,
        float $parentPrice,
        string $expectedFirstVariantId,
        float $firstVariantPrice,
        string $expectedSecondVariantId,
        float $secondVariantPrice,
        string $expectedThirdVariantId,
        float $thirdVariantPrice
    ): ?ProductEntity {
        $firstOptionId = Uuid::randomHex();
        $secondOptionId = Uuid::randomHex();
        $thirdOptionId = Uuid::randomHex();
        $optionGroupId = Uuid::randomHex();

        $variants = [];
        $variants[] = $this->getBasicVariantData([
            'id' => $expectedFirstVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.1',
            'name' => 'FINDOLOGIC VARIANT 1',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $firstVariantPrice,
                    'net' => $firstVariantPrice,
                    'linked' => false
                ]
            ],
            'options' => [
                ['id' => $firstOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedSecondVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.2',
            'name' => 'FINDOLOGIC VARIANT 2',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $secondVariantPrice,
                    'net' => $secondVariantPrice,
                    'linked' => false
                ]
            ],
            'options' => [
                ['id' => $secondOptionId]
            ],
        ]);

        $variants[] = $this->getBasicVariantData([
            'id' => $expectedThirdVariantId,
            'parentId' => $parentId,
            'productNumber' => 'FINDOLOGIC001.3',
            'name' => 'FINDOLOGIC VARIANT 3',
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $thirdVariantPrice,
                    'net' => $thirdVariantPrice,
                    'linked' => false
                ]
            ],
            'options' => [
                ['id' => $thirdOptionId]
            ],
        ]);

        return $this->createVisibleTestProductWithCustomVariants([
            'id' => $parentId,
            'active' => false,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => $parentPrice,
                    'net' => $parentPrice,
                    'linked' => false
                ]
            ],
            'configuratorSettings' => [
                [
                    'option' => [
                        'id' => $firstOptionId,
                        'name' => 'Red',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $secondOptionId,
                        'name' => 'Orange',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
                [
                    'option' => [
                        'id' => $thirdOptionId,
                        'name' => 'Green',
                        'group' => [
                            'id' => $optionGroupId,
                            'name' => 'Color',
                        ],
                    ],
                ],
            ]
        ], $variants);
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
            $criteria->addAssociations(Constants::PRODUCT_ASSOCIATIONS);
            $criteria->addAssociations(Constants::VARIANT_ASSOCIATIONS);
            $criteria->addAssociation('visibilities');

            $product = $this->getContainer()->get('product.repository')->search($criteria, $context)->first();

            /** @var ProductEntity $sdkProduct */
            $sdkProduct = Utils::createSdkEntity(ProductEntity::class, $product);
            return $sdkProduct;
        } catch (InconsistentCriteriaIdsException $e) {
            return null;
        }
    }

    public function createCustomer(string $customerId, $customerGroup = null): void
    {
        $password = 'foo';
        $email = 'foo@bar.de';
        $addressId = Uuid::randomHex();

        if ($customerGroup === null) {
            $customerGroup = Defaults::FALLBACK_CUSTOMER_GROUP;
        }

        $this->getContainer()->get('customer.repository')->upsert(
            [
                [
                    'id' => $customerId,
                    'salesChannelId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
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
                    'groupId' => $customerGroup,
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
                    'salesChannelId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
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
