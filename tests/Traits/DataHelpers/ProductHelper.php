<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait ProductHelper
{
    public function createTestProduct(array $data = [], bool $withVariant = false): ?ProductEntity
    {
        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $newCategoryId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $contextFactory = $container->get(SalesChannelContextFactory::class);
        /** @var SalesChannelContext $salesChannelContext */
        $salesChannelContext = $contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $navigationCategoryId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $categoryData = [
            [
                'id' => Uuid::randomHex(),
                'name' => 'FINDOLOGIC Main 2',
                'children' => [
                    [
                        'id' => $newCategoryId,
                        'name' => 'FINDOLOGIC Sub'
                    ]
                ]
            ]
        ];
        $container->get('category.repository')->upsert($categoryData, $context);

        /** @var EntityRepository $localeRepo */
        $localeRepo = $container->get('language.repository');
        /** @var LanguageEntity $language */
        $language = $localeRepo->search(new Criteria(), Context::createDefaultContext())->first();

        $defaultLanguageId = $salesChannelContext->getSalesChannel()->getLanguageId();

        /** @var EntityRepository $salesChannelRepo */
        $salesChannelRepo = $container->get('sales_channel.repository');
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepo->search(new Criteria(), Context::createDefaultContext())->last();

        $seoUrlRepo = $container->get('seo_url.repository');
        $seoUrls = $seoUrlRepo->search(new Criteria(), Context::createDefaultContext());
        $seoUrlsStoreFrontContext = $seoUrlRepo->search(new Criteria(), $contextFactory->create(
            Uuid::randomHex(),
            $salesChannel->getId()
        )->getContext());

        $productSeoUrls = [];
        if ($seoUrls->count() === 0 && $seoUrlsStoreFrontContext->count() === 0) {
            $productSeoUrls = [
                [
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'Awesome-Seo-Url/&ecause/SÄÖ/is/$mportant+',
                    'isCanonical' => true,
                    'routeName' => 'frontend.detail.page'
                ],
                [
                    'id' => Uuid::randomHex(),
                    'foreignKey' => Uuid::randomHex(),
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'I-Should-Be-Used/Because/Used/Language',
                    'isCanonical' => true,
                    'routeName' => 'frontend.detail.page',
                    'languageId' => $language->getId(),
                    'salesChannelId' => $salesChannel->getId()
                ],
                [
                    'id' => Uuid::randomHex(),
                    'foreignKey' => Uuid::randomHex(),
                    'pathInfo' => '/detail/' . $id,
                    'seoPathInfo' => 'Awesome-Seo-Url/&ecause/SÄÖ/is/$mportant+',
                    'isCanonical' => true,
                    'routeName' => 'frontend.detail.page',
                    'languageId' => $defaultLanguageId,
                    'salesChannelId' => $salesChannel->getId()
                ],
            ];
        }

        $productData = [
            'id' => $id,
            'productNumber' => 'FINDOLOGIC001',
            'stock' => 10,
            'ean' => Uuid::randomHex(),
            'description' => 'FINDOLOGIC Description',
            'tags' => [
                ['id' => Uuid::randomHex(), 'name' => 'FINDOLOGIC Tag']
            ],
            'name' => 'FINDOLOGIC Product',
            'manufacturerNumber' => Uuid::randomHex(),
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'FINDOLOGIC'],
            'tax' => ['name' => '9%', 'taxRate' => 9],
            'categories' => [
                [
                    'parentId' => $navigationCategoryId,
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
                'en-GB' => [
                    'customTranslated' => [
                        'root' => 'FINDOLOGIC Translated',
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

        $productInfo = [];
        // Main product data
        $productInfo[] = array_merge($productData, $data);

        if ($withVariant) {
            // Standard variant data
            $variantData = [
                'id' => Uuid::randomHex(),
                'productNumber' => 'FINDOLOGIC001.1',
                'name' => 'FINDOLOGIC VARIANT',
                'stock' => 10,
                'active' => true,
                'parentId' => $id,
                'tax' => ['name' => '9%', 'taxRate' => 9],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]]
            ];

            $productInfo[] = array_merge($variantData, $data);
        }

        $container->get('product.repository')->upsert($productInfo, $context);

        try {
            $criteria = new Criteria([$id]);
            $criteria = Utils::addProductAssociations($criteria);

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

    public function createCustomer(string $customerID): void
    {
        $password = 'foo';
        $email = 'foo@bar.de';
        $addressId = Uuid::randomHex();

        $this->getContainer()->get('customer.repository')->upsert(
            [
                [
                    'id' => $customerID,
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
}
