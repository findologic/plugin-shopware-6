<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\FinSearch\Export\Adapters\AttributeAdapter;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\AttributeHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\CategoryHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AttributeAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use AttributeHelper;

    /** @var SalesChannelContext */
    protected $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        DynamicProductGroupService::getInstance(
            $this->getContainer(),
            $this->getContainer()->get('serializer.mapping.cache.symfony'),
            Context::createDefaultContext(),
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            0
        );
        $this->getContainer()->set(
            'fin_search.export_context',
            new ExportContext(
                'ABCDABCDABCDABCDABCDABCDABCDABCD',
                [],
                $this->getCategory()
            )
        );
    }

    public function testAttributeContainsAttributeOfTheProduct(): void
    {
        $adapter = $this->getContainer()->get(AttributeAdapter::class);
        $product = $this->createTestProduct();
        $expected = $this->getAttributes($product, IntegrationType::API);

        $attribute = $adapter->adapt($product);

        $this->assertEquals($expected, $attribute);
    }
}
