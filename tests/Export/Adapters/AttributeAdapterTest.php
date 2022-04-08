<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Export\Adapters\AttributeAdapter;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\FindologicProductFactory;
use FINDOLOGIC\FinSearch\Export\UrlBuilderService;
use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\IntegrationType;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\AttributeHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\CategoryHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AttributeAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;
    use AttributeHelper;
    use ConfigHelper;

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

    /**
     * @dataProvider attributeProvider
     */
    public function testAttributesAreProperlyEscaped(
        string $integrationType,
        string $attributeName,
        string $expectedName
    ): void {
        $config = $this->getMockedConfig($integrationType);

        $adapter = new AttributeAdapter(
            $config,
            $this->getContainer()->get('fin_search.dynamic_product_group'),
            $this->getContainer()->get(Translator::class),
            $this->getContainer()->get('fin_search.sales_channel_context'),
            $this->getContainer()->get(UrlBuilderService::class),
            $this->getContainer()->get('fin_search.export_context'),
        );

        $productEntity = $this->createTestProduct(
            [
                'properties' => [
                    [
                        'id' => Uuid::randomHex(),
                        'name' => 'some value',
                        'group' => [
                            'id' => Uuid::randomHex(),
                            'name' => $attributeName
                        ],
                    ]
                ]
            ]
        );

        $attributes = $adapter->adapt($productEntity);

        $foundAttributes = array_filter(
            $attributes,
            static function (Attribute $attribute) use ($expectedName) {
                return $attribute->getKey() === $expectedName;
            }
        );

        /** @var Attribute $attribute */
        $attribute = reset($foundAttributes);
        $this->assertInstanceOf(
            Attribute::class,
            $attribute,
            sprintf('Attribute "%s" not present in attributes.', $expectedName)
        );
    }

    private function getMockedConfig(string $integrationType = 'Direct Integration'): Config
    {
        $override = [
            'languageId' => $this->salesChannelContext->getSalesChannel()->getLanguageId(),
            'salesChannelId' => $this->salesChannelContext->getSalesChannel()->getId()
        ];

        return $this->getFindologicConfig($override, $integrationType === 'Direct Integration');
    }

    public function attributeProvider(): array
    {
        return [
            'API Integration filter with some special characters' => [
                'integrationType' => 'API',
                'attributeName' => 'Special Characters /#+*()()=§(=\'\'!!"$.|',
                'expectedName' => 'SpecialCharacters'
            ],
            'API Integration filter with brackets' => [
                'integrationType' => 'API',
                'attributeName' => 'Farbwiedergabe (Ra/CRI)',
                'expectedName' => 'FarbwiedergabeRaCRI'
            ],
            'API Integration filter with special UTF-8 characters' => [
                'integrationType' => 'API',
                'attributeName' => 'Ausschnitt D ø (mm)',
                'expectedName' => 'AusschnittDmm'
            ],
            'API Integration filter dots and dashes' => [
                'integrationType' => 'API',
                'attributeName' => 'free_shipping.. Really Cool--__',
                'expectedName' => 'free_shippingReallyCool--__'
            ],
            'API Integration filter with umlauts' => [
                'integrationType' => 'API',
                'attributeName' => 'Umläüts äre cööl',
                'expectedName' => 'Umläütsärecööl'
            ],
            'Direct Integration filter with some special characters' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Special Characters /#+*()()=§(=\'\'!!"$.|',
                'expectedName' => 'Special Characters /#+*()()=§(=\'\'!!"$.|'
            ],
            'Direct Integration filter with brackets' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Farbwiedergabe (Ra/CRI)',
                'expectedName' => 'Farbwiedergabe (Ra/CRI)'
            ],
            'Direct Integration filter with special UTF-8 characters' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Ausschnitt D ø (mm)',
                'expectedName' => 'Ausschnitt D ø (mm)'
            ],
            'Direct Integration filter dots and dashes' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'free_shipping.. Really Cool--__',
                'expectedName' => 'free_shipping.. Really Cool--__'
            ],
            'Direct Integration filter with umlauts' => [
                'integrationType' => 'Direct Integration',
                'attributeName' => 'Umläüts äre cööl',
                'expectedName' => 'Umläüts äre cööl'
            ],
        ];
    }
}
