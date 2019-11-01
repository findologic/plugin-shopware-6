<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Request;

use FINDOLOGIC\FinSearch\Findologic\Request\SearchRequestFactory;
use FINDOLOGIC\FinSearch\Struct\Config;
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

    /**
     * @dataProvider pluginVersionProvider
     * @throws InvalidArgumentException
     * @throws InconsistentCriteriaIdsException
     */
    public function testPluginVersionIfCached(
        bool $isCached,
        ?string $cachedVersion,
        string $expectedVersion
    ): void {
        $cacheKey = 'finsearch_version';

        /** @var CacheItemPoolInterface|MockObject $cachePoolMock */
        $cachePoolMock = $this->getMockBuilder(CacheItemPoolInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        /** @var CacheItemInterface|MockObject $cacheItemMock */
        $cacheItemMock = $this->getMockBuilder(CacheItemInterface::class)->disableOriginalConstructor()->getMock();
        if ($isCached) {
            $cachePoolMock->expects($this->never())->method('save');
        } else {
            $cachePoolMock->expects($this->once())->method('save');
        }

        $cacheItemMock->expects($isCached ? $this->exactly(2) : $this->once())
            ->method('get')
            ->willReturn($cachedVersion);

        $cachePoolMock->expects($this->once())
            ->method('getItem')
            ->with($cacheKey)
            ->willReturn($cacheItemMock);

        $searchRequestFactory = new SearchRequestFactory($cachePoolMock, $this->getContainer());

        /** @var Config|MockObject $config */
        $config = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->getMock();

        $request = new Request();
        $request->headers->set('referer', 'http://localhost.shopware');

        $searchRequest = $searchRequestFactory->getInstance(
            $config,
            $request
        );

        $params = $searchRequest->getParams();
        $this->assertSame($expectedVersion, $params['revision']);
    }

    public function pluginVersionProvider()
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
}
