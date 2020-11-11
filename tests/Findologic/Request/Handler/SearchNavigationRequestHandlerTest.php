<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request\Handler;

use FINDOLOGIC\Api\Client as ApiClient;
use FINDOLOGIC\Api\Config as ApiConfig;
use FINDOLOGIC\Api\Requests\SearchNavigation\SearchNavigationRequest;
use FINDOLOGIC\FinSearch\Findologic\Request\FindologicRequestFactory;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\NavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchNavigationRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Request\Handler\SearchRequestHandler;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\HttpFoundation\Request;

class SearchNavigationRequestHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /** @var Config|MockObject */
    private $configMock;

    /** @var ApiConfig */
    private $apiConfig;

    /** @var ApiClient */
    private $apiClientMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->apiConfig = new ApiConfig('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $this->apiClientMock = $this->getMockBuilder(ApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function requestHandlerProvider(): array
    {
        return [
            'search request handler' => $this->buildSearchRequestHandler(),
            'navigation request handler' => $this->buildNavigationRequestHandler(),
        ];
    }

    /**
     * @dataProvider requestHandlerProvider
     */
    public function testAddsUserGroupHash(SearchNavigationRequestHandler $requestHandler): void
    {
//        $event = $requestHandler instanceof NavigationRequestHandler ? new ProductListingCriteriaEvent()
        $requestHandler->handleRequest($request);
    }

    private function buildSearchRequestHandler(): SearchRequestHandler
    {
        return new SearchRequestHandler(
            $this->getContainer()->get(ServiceConfigResource::class),
            $this->getContainer()->get(FindologicRequestFactory::class),
            $this->configMock,
            $this->apiConfig,
            $this->apiClientMock
        );
    }

    private function buildNavigationRequestHandler(): NavigationRequestHandler
    {
        return new NavigationRequestHandler(
            $this->getContainer()->get(ServiceConfigResource::class),
            $this->getContainer()->get(FindologicRequestFactory::class),
            $this->configMock,
            $this->apiConfig,
            $this->apiClientMock,
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer()
        );
    }
}
