<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Storefront\Controller;

use FINDOLOGIC\FinSearch\Findologic\Api\FindologicSearchService;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\FilterHandler;
use FINDOLOGIC\FinSearch\Storefront\Controller\SearchController as FindologicSearchController;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\PluginConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\WithTestClient;
use FINDOLOGIC\FinSearch\Utils\Utils;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class SearchControllerTest extends TestCase
{
    use WithTestClient;
    use SalesChannelHelper;
    use PluginConfigHelper;
    use StorefrontControllerTestBehaviour;

    private const VALID_SHOPKEY = 'ABCDABCDABCDABCDABCDABCDABCDABCD';

    /** @var SalesChannelContext */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildSalesChannelContext();
    }

    public function isFindologicActiveProvider(): array
    {
        return [
            'Findologic is active' => [true],
            'Findologic is disabled' => [false],
        ];
    }

    /**
     * @dataProvider isFindologicActiveProvider
     */
    public function testFilterDisableRequestIsCalled(bool $active): void
    {
        if (Utils::versionLowerThan('6.3.3.0')) {
            $this->markTestSkipped('Filter disabling feature was introduced in Shopware 6.3.3.0');
        }

        $request = new Request(['search' => 'abc']);
        if ($active) {
            $this->enableFindologicPlugin($this->getContainer(), self::VALID_SHOPKEY, $this->salesChannelContext);
        }
        $mockFindologicService = $this->getMockBuilder(FindologicSearchService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockFindologicService->expects($this->once())->method('allowRequest')->willReturn($active);

        $filters = [];
        $mockFilterHandler = $this->getMockBuilder(FilterHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $invokeCount = $active ? $this->once() : $this->never();
        $mockFilterHandler->expects($invokeCount)->method('handleAvailableFilters')->willReturn($filters);
        $controller = new FindologicSearchController(
            $this->getContainer()->get('FINDOLOGIC\FinSearch\Storefront\Controller\SearchController.inner'),
            null,
            $mockFilterHandler,
            $this->getContainer(),
            $mockFindologicService
        );

        $response = $controller->filter($request, $this->salesChannelContext);
        $this->assertTrue($response->isOk());
    }
}
