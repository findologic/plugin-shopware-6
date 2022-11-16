<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupCollection;
use Vin\ShopwareSdk\Data\Entity\CustomerGroup\CustomerGroupEntity;

trait AdvancedPriceHelper
{
    use ProductHelper;

    public function createRules(array $groupsData): void
    {
        foreach ($groupsData as $group) {
            $rule = [
                'id' => $group['ruleId'],
                'name' => 'Test rule',
                'priority' => 1,
                'conditions' => [
                    [
                        'type' => (new CustomerGroupRule())->getName(),
                        'value' => [
                            'customerGroupIds' => [$group['groupId']],
                            'operator' => CustomerGroupRule::OPERATOR_EQ,
                        ],
                    ],
                ]];

            $this->getContainer()->get('rule.repository')->create([
                $rule
            ], $this->salesChannelContext->getContext());
        }
    }

    public function createCustomerGroups($groupData): void
    {
        foreach ($groupData as $group) {
            $data = [
                'id' => $group['groupId'],
                'displayGross' => $group['displayGross'],
                'translations' => [
                    'en-GB' => [
                        'name' => 'Net price customer group',
                    ],
                    'de-DE' => [
                        'name' => 'Nettopreis-Kundengruppe',
                    ],
                ],
            ];

            $this->getContainer()
                ->get('customer_group.repository')
                ->create([$data], $this->salesChannelContext->getContext());
        }
    }

    public function getPrices(array $groupsData): array
    {
        $prices = [];

        foreach ($groupsData as $groupData) {
            foreach ($groupData['prices'] as $price) {
                $prices[] = [
                    'quantityStart' => $price['qtyMin'],
                    'quantityEnd' => $price['qtyMax'],
                    'ruleId' => $groupData['ruleId'],
                    'price' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => $price['gross'],
                            'net' => $price['net'],
                            'linked' => false
                        ],
                    ],
                ];
            }
        }

        return $prices;
    }

    public function createCustomers(array $groupsData): void
    {
        foreach ($groupsData as $groupData) {
            $this->createCustomer($groupData['customerId'], $groupData['groupId']);
        }
    }

    public function generateCustomers(array $groupsData): CustomerGroupCollection
    {
        $customerGroups = new CustomerGroupCollection();

        foreach ($groupsData as $groupData) {
            $customerGroup = new CustomerGroupEntity();
            $customerGroup->id = $groupData['groupId'];
            $customerGroup->displayGross = $groupData['displayGross'];

            $customerGroups->add($customerGroup);
        }

        return $customerGroups;
    }

    public function getAdvancedPricesTestGroupData(array $groupsData): array
    {
        $testData = [];

        foreach ($groupsData as $groupData) {
            $priceData = [];
            $caseData =  [
                'groupId' => $groupData['groupId'],
                'customerId' => Uuid::randomHex(),
                'ruleId' => Uuid::randomHex(),
                'displayGross' => $groupData['displayGross'],
            ];

            foreach ($groupData['prices'] as $price) {
                $priceData[] =  [
                    'qtyMin' => $price['qtyMin'],
                    'qtyMax' => $price['qtyMax'],
                    'gross' => $price['gross'],
                    'net' => $price['net']
                ];
            }

            $caseData['prices'] = $priceData;

            $testData[] = $caseData;
        }

        return $testData;
    }
}
