<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Findologic\Response;

use FINDOLOGIC\Api\Responses\Autocomplete\SuggestResponse;
use FINDOLOGIC\Api\Responses\Html\GenericHtmlResponse;
use FINDOLOGIC\Api\Responses\Response;
use FINDOLOGIC\Api\Responses\Xml21\Xml21Response;
use FINDOLOGIC\FinSearch\Findologic\Resource\ServiceConfigResource;
use FINDOLOGIC\FinSearch\Findologic\Response\ResponseParser;
use FINDOLOGIC\FinSearch\Findologic\Response\Xml21ResponseParser;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ConfigHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\MockResponseHelper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ResponseParserTest extends TestCase
{
    use MockResponseHelper;
    use ConfigHelper;

    public function unsupportedResponseInstanceProvider(): array
    {
        return [
            'generic HTML response' => [
                'response' => new GenericHtmlResponse('')
            ],
            'suggest response' => [
                'response' => new SuggestResponse('{}')
            ],
        ];
    }

    /**
     * @dataProvider unsupportedResponseInstanceProvider
     */
    public function testExceptionIsThrownWhenTryingToGetAnUnsupportedInstance(Response $response): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported response format.');

        $serviceConfigResourceMock = $this->createMock(ServiceConfigResource::class);
        ResponseParser::getInstance($serviceConfigResourceMock, $response, $this->getShopkey());
    }

    public function supportedResponseInstanceProvider(): array
    {
        return [
            'XML 2.1 response' => [
                'response' => new Xml21Response($this->getMockResponse()),
                'expectedParser' => Xml21ResponseParser::class
            ],
        ];
    }

    /**
     * @dataProvider supportedResponseInstanceProvider
     */
    public function testExpectedResponseParserIsReturnedForSupportedResponseInstances(
        Response $response,
        string $expectedParser
    ): void {
        $serviceConfigResourceMock = $this->createMock(ServiceConfigResource::class);
        $parser = ResponseParser::getInstance($serviceConfigResourceMock, $response, $this->getShopkey());

        $this->assertInstanceOf($expectedParser, $parser);
    }
}
