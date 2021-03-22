<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

trait OrderHelper
{
    public function createOrder(string $customerId, array $overrideData = []): void
    {
        $data = [
            'id' => $this->ids->create('test-order'),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(
                10,
                10,
                10,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_NET
            ),
            'shippingCosts' => new CalculatedPrice(
                10,
                10,
                new CalculatedTaxCollection(),
                new TaxRuleCollection()
            ),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $this->fetchFirstIdFromTable('state_machine'),
            'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'billingAddressId' => Uuid::randomHex(),
            'addresses' => [
                [
                    'id' => Uuid::randomHex(),
                    'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->fetchFirstIdFromTable('country'),
                ],
            ],
            'lineItems' => [
                [
                    'id' => Uuid::randomHex(),
                    'identifier' => 'test',
                    'quantity' => 1,
                    'type' => 'test',
                    'label' => 'test',
                    'price' => new CalculatedPrice(
                        10,
                        10,
                        new CalculatedTaxCollection(),
                        new TaxRuleCollection()
                    ),
                    'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection(), 2),
                    'good' => true,
                    'payload' => ['productNumber' => 'test']
                ],
            ],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];

        $data = array_merge($data, $overrideData);
        $this->getContainer()->get('order.repository')->create([$data], Context::createDefaultContext());
    }
}
