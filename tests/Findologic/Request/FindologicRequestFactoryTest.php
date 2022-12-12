<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request;

use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class FindologicRequestFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function ipAddressProvider(): array
    {
        return [
            'Single IP' => [
                'HTTP_CLIENT_IP',
                '192.168.0.1',
                '192.168.0.1'
            ],
            'Same IP twice separated by comma' => [
                'HTTP_CLIENT_IP',
                '192.168.0.1,192.168.0.1',
                '192.168.0.1'
            ],
            'Same IP twice separated by comma and space' => [
                'HTTP_CLIENT_IP',
                '192.168.0.1, 192.168.0.1',
                '192.168.0.1'
            ],
            'Different IPs separated by comma' => [
                'HTTP_CLIENT_IP',
                '192.168.0.1,10.10.0.200',
                '192.168.0.1,10.10.0.200'
            ],
            'Different IPs separated by comma and space' => [
                'HTTP_CLIENT_IP',
                '192.168.0.1, 10.10.0.200',
                '192.168.0.1,10.10.0.200'
            ]
        ];
    }

    public function reverseProxyIpAddressProvider(): array
    {
        return [
            'Single IP' => [
                'HTTP_X_FORWARDED_FOR',
                '192.168.0.1',
                '192.168.0.1'
            ],
            'Same IP twice separated by comma' => [
                'HTTP_X_FORWARDED_FOR',
                '192.168.0.1,192.168.0.1',
                '192.168.0.1'
            ],
            'Same IP twice separated by comma and space' => [
                'HTTP_X_FORWARDED_FOR',
                '192.168.0.1, 192.168.0.1',
                '192.168.0.1'
            ],
            'Different IPs separated by comma' => [
                'HTTP_X_FORWARDED_FOR',
                '192.168.0.1,10.10.0.200',
                '192.168.0.1'
            ],
            'Different IPs separated by comma and space' => [
                'HTTP_X_FORWARDED_FOR',
                '192.168.0.1, 10.10.0.200',
                '192.168.0.1'
            ]
        ];
    }

    /**
     * @dataProvider ipAddressProvider
     * @dataProvider reverseProxyIpAddressProvider
     *
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function testClientIpAddresses(
        string $ipKey,
        string $ipAddress,
        string $expectedIpAddress
    ): void {
        $cacheKey = 'finsearch_version';
        $expectedReferer = 'http://localhost.shopware';
        $expectedHost = 'findologic.de';

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        $cachePoolMock->expects($this->never())->method('save');
        $cacheItemMock->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls('1.0.0', '1.0.0');

        $cachePoolMock->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        $searchRequestFactory = new SearchRequestFactory(
            $cachePoolMock,
            $this->getContainer()->get('plugin.repository'),
            $this->getContainer()->getParameter('kernel.shopware_version')
        );

        $request = new Request();
        $request->headers->set('referer', $expectedReferer);
        $request->headers->set('host', $expectedHost);
        $_SERVER[$ipKey] = $ipAddress;

        $searchRequest = $searchRequestFactory->getInstance($request);

        $params = $searchRequest->getParams();
        $this->assertSame($expectedIpAddress, $params['userip']);
    }
}
