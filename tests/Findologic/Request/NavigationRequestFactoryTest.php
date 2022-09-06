<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request;

use FINDOLOGIC\Api\Requests\SearchNavigation\NavigationRequest;
use FINDOLOGIC\FinSearch\Findologic\Request\NavigationRequestFactory;
use FINDOLOGIC\FinSearch\Utils\Utils;
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
        $expectedShopType = 'Shopware6';
        $expectedShopVersion = $this->getContainer()->getParameter('kernel.shopware_version');

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cachePoolMock->expects($this->once())->method('save');

        $cacheItemMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, $expectedVersion);

        $cachePoolMock->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        $navigationRequestFactory = new NavigationRequestFactory(
            $cachePoolMock,
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->getParameter('kernel.shopware_version')
        );

        $request = new Request();
        $request->headers->set('referer', $expectedReferer);
        $request->headers->set('host', $expectedHost);
        $request->server->set('REMOTE_ADDR', $expectedIpAddress);

        $navigationRequest = $navigationRequestFactory->getInstance($request);
        $navigationRequest->setSelected('cat', $expectedCategoryPath);

        $this->assertInstanceOf(NavigationRequest::class, $navigationRequest);

        $params = $navigationRequest->getParams();
        $this->assertSame($expectedVersion, $params['revision']);

        // Test other parameters are passed correctly
        $this->assertSame($expectedReferer, $params['referer']);
        $this->assertSame($expectedIpAddress, $params['userip']);
        $this->assertSame($expectedAdapter, $params['outputAdapter']);
        $this->assertSame($expectedHost, $params['shopurl']);
        $this->assertSame($expectedCategoryPath, $params['selected']['cat'][0]);
        $this->assertSame($expectedShopType, $params['shopType']);
        $this->assertSame($expectedShopVersion, $params['shopVersion']);
    }
}
