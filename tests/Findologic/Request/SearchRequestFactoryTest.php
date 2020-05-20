<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request;

use FINDOLOGIC\Api\Requests\SearchNavigation\SearchRequest;
use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class SearchRequestFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function pluginVersionProvider(): array
    {
        return [
            'Plugin version is cached' => [
                'isCached' => true,
                'cachedVersion' => '1.0.0',
                'expectedVersion' => '1.0.0'
            ],
            'Plugin version is not cached' => [
                'isCached' => false,
                'cachedVersion' => null,
                'expectedVersion' => '0.1.0'
            ]
        ];
    }

    /**
     * @dataProvider pluginVersionProvider
     *
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function testPluginVersionIfCached(
        bool $isCached,
        ?string $cachedVersion,
        string $expectedVersion
    ): void {
        $cacheKey = 'finsearch_version';
        $expectedReferer = 'http://localhost.shopware';
        $expectedIpAddress = '192.168.0.1';
        $expectedHost = 'findologic.de';
        $expectedAdapter = 'XML_2.1';

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        if ($isCached) {
            $cachePoolMock->expects(static::never())->method('save');
        } else {
            $cachePoolMock->expects(static::once())->method('save');
        }

        $cacheItemMock->expects(static::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls($cachedVersion, $expectedVersion);

        $cachePoolMock->expects(static::once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        $searchRequestFactory = new SearchRequestFactory($cachePoolMock, $this->getContainer());

        $request = new Request();
        $request->headers->set('referer', $expectedReferer);
        $request->headers->set('host', $expectedHost);
        $request->server->set('REMOTE_ADDR', $expectedIpAddress);

        $searchRequest = $searchRequestFactory->getInstance($request);

        static::assertInstanceOf(SearchRequest::class, $searchRequest);

        $params = $searchRequest->getParams();
        static::assertSame($expectedVersion, $params['revision']);

        // Test other parameters are passed correctly
        static::assertSame($expectedReferer, $params['referer']);
        static::assertSame($expectedIpAddress, $params['userip']);
        static::assertSame($expectedAdapter, $params['outputAdapter']);
        static::assertSame($expectedHost, $params['shopurl']);
    }
}
