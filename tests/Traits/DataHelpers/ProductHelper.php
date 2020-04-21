<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Test\Payment\Handler\SyncTestPaymentHandler;
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
        $categoryId = Uuid::randomHex();
        $redId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $productData = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
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
            ],
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

        $productData = array_merge($productData, $data);

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
            'points' => $points,
            'title' => $title,
        ];

        $this->getContainer()->get('product_review.repository')->upsert([$data], $this->defaultContext);
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
