<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request;

use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class NavigationRequestFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function testNavigationRequestInstance(): void
    {
        $cacheKey = 'finsearch_version';
        $expectedReferer = 'http://localhost.shopware';
        $expectedIpAddress = '192.168.0.1';
        $expectedHost = 'findologic.de';
        $expectedAdapter = 'XML_2.1';
        $expectedVersion = '0.1.0';
        $expectedCategoryPath = 'Kids & Music_Computers & Shoes';

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cachePoolMock->expects(static::once())->method('save');

        $cacheItemMock->expects(static::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, $expectedVersion);

        $cachePoolMock->expects(static::once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        $navigationRequestFactory = new NavigationRequestFactory($cachePoolMock, $this->getContainer());

        $request = new Request();
        $request->headers->set('referer', $expectedReferer);
        $request->headers->set('host', $expectedHost);
        $request->server->set('REMOTE_ADDR', $expectedIpAddress);

        $navigationRequest = $navigationRequestFactory->getInstance($request);
        $navigationRequest->setSelected('cat', $expectedCategoryPath);

        static::assertInstanceOf(NavigationRequest::class, $navigationRequest);

        $params = $navigationRequest->getParams();
        static::assertSame($expectedVersion, $params['revision']);

        // Test other parameters are passed correctly
        static::assertSame($expectedReferer, $params['referer']);
        static::assertSame($expectedIpAddress, $params['userip']);
        static::assertSame($expectedAdapter, $params['outputAdapter']);
        static::assertSame($expectedHost, $params['shopurl']);
        static::assertSame($expectedCategoryPath, $params['selected']['cat'][0]);
    }
}
