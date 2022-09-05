<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Adapters\Export\Adapters;

use FINDOLOGIC\Export\Data\Usergroup;
use FINDOLOGIC\FinSearch\Export\Adapters\UserGroupsAdapter;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class UserGroupsAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;

    protected SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->getContainer()->set('fin_search.export_context', new ExportContext(
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            [],
            $this->salesChannelContext->getSalesChannel()->getNavigationCategory()
        ));
    }

    public function testUserGroupsContainsTheUserGroupsOfTheProduct(): void
    {
        $userGroup = new CustomerGroupEntity();
        $userGroup->setId(Uuid::randomHex());

        $exportContext = $this->getContainer()->get('fin_search.export_context');
        $exportContext->setCustomerGroups([$userGroup]);

        $expectedUserGroup = new Usergroup(
            Utils::calculateUserGroupHash($exportContext->getShopkey(), $userGroup->getId())
        );

        $adapter = $this->getContainer()->get(UserGroupsAdapter::class);
        $product = $this->createTestProduct([]);

        $userGroups = $adapter->adapt($product);

        $this->assertCount(1, $userGroups);
        $this->assertEquals($expectedUserGroup, $userGroups[0]);
    }
}
