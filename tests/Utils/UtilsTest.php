<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Utils;

use FINDOLOGIC\FinSearch\Findologic\Config\FindologicConfigService;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Struct\Config;
use FINDOLOGIC\FinSearch\Struct\FindologicService;
use FINDOLOGIC\FinSearch\Utils\Utils;
use InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class UtilsTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function findologicActiveProvider(): array
    {
        return [
            'Plugin is inactive' => [
                'isActive' => false,
                'isActiveOnCategory' => false,
                'isDirectIntegration' => false,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => false,
            ],
            'Plugin is active' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => true,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active but staging is enabled' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => false,
            ],
            'Plugin is active but staging is enabled and findologic=on is set' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => false,
                'expectedFindologicActive' => true,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and direct integration' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => true,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and direct integration with staging' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => true,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and direct integration with staging but without findologic=on' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => true,
                'isStagingShop' => true,
                'isStagingSession' => false,
                'isCategoryPage' => false,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true, // Smart Suggest handles findologic=on in case of DI.
            ],
            'Plugin is active and with staging on category page' => [
                'isActive' => true,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => false,
                'expectedFindologicActive' => true,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is active and with staging on category page but setting is inactive' => [
                'isActive' => true,
                'isActiveOnCategory' => false,
                'isDirectIntegration' => false,
                'isStagingShop' => true,
                'isStagingSession' => true,
                'isCategoryPage' => true,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => true,
            ],
            'Plugin is inactive and user is on category page' => [
                'isActive' => false,
                'isActiveOnCategory' => true,
                'isDirectIntegration' => false,
                'isStagingShop' => false,
                'isStagingSession' => false,
                'isCategoryPage' => true,
                'expectedFindologicActive' => false,
                'expectedSmartSuggestActive' => false,
            ],
        ];
    }

    /**
     * @dataProvider findologicActiveProvider
     */
    public function testFindologicActive(
        bool $isActive,
        bool $isActiveOnCategory,
        bool $isDirectIntegration,
        bool $isStagingShop,
        bool $isStagingSession,
        bool $isCategoryPage,
        bool $expectedFindologicActive,
        bool $expectedSmartSuggestActive
    ): void {
        /** @var Session|MockObject $sessionMock */
        $sessionMock = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request = new Request($isStagingSession ? ['findologic' => 'on'] : []);
        $request->setSession($sessionMock);
        $context = Context::createDefaultContext();
        /** @var ServiceConfigResource|MockObject $serviceConfigResourceMock */
        $serviceConfigResourceMock = $this->getMockBuilder(ServiceConfigResource::class)
            ->disableOriginalConstructor()
            ->getMock();
        $serviceConfigResourceMock->expects($this->any())->method('isDirectIntegration')
            ->willReturn($isDirectIntegration);
        $serviceConfigResourceMock->expects($this->any())->method('isStaging')
            ->willReturn($isStagingShop);
        /** @var Config|MockObject $configMock */
        $configMock = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $configMock->expects($this->any())->method('isInitialized')->willReturn(true);
        $configMock->expects($this->any())->method('isActive')->willReturn($isActive);
        $configMock->expects($this->any())->method('getShopkey')
            ->willReturn('ABCDABCDABCDABCDABCDABCDABCDABCD');
        $configMock->expects($this->any())->method('isActiveOnCategoryPages')
            ->willReturn($isActiveOnCategory);

        $shouldHandleRequest = Utils::shouldHandleRequest(
            $request,
            $context,
            $serviceConfigResourceMock,
            $configMock,
            $isCategoryPage
        );

        /** @var FindologicService $findologicService */
        $findologicService = $context->getExtension('findologicService');

        $this->assertSame($expectedFindologicActive, $shouldHandleRequest);
        $this->assertSame($expectedFindologicActive, $findologicService->getEnabled());
        $this->assertSame($expectedSmartSuggestActive, $findologicService->getSmartSuggestEnabled());
    }

    public function testShouldHandleRequestThrowsExceptionInCaseConfigIsNotInitialized(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Config needs to be initialized first!');

        /** @var FindologicConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(FindologicConfigService::class);

        $nonInitializedConfig = new Config(
            $systemConfigService,
            $this->getContainer()->get(ServiceConfigResource::class)
        );

        Utils::shouldHandleRequest(
            new Request(),
            Context::createDefaultContext(),
            $this->getContainer()->get(ServiceConfigResource::class),
            $nonInitializedConfig
        );
    }
}
