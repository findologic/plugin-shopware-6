<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Export;

use Exception;
use FINDOLOGIC\Export\Exceptions\EmptyValueNotAllowedException;
use FINDOLOGIC\Export\XML\XMLItem;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Export\Adapters\AdapterFactory;
use FINDOLOGIC\FinSearch\Export\Adapters\NameAdapter;
use FINDOLOGIC\FinSearch\Export\DynamicProductGroupService;
use FINDOLOGIC\FinSearch\Export\ExportContext;
use FINDOLOGIC\FinSearch\Export\ExportItemAdapter;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\ProductHelper;
use FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers\SalesChannelHelper;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Throwable;

class ExportItemAdapterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelHelper;
    use ProductHelper;

    private $loggerMock = null;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->buildSalesChannelContext();
        $this->getContainer()->set('fin_search.sales_channel_context', $this->salesChannelContext);
        $this->getContainer()->set(
            'fin_search.export_context',
            new ExportContext(
                'ABCDABCDABCDABCDABCDABCDABCDABCD',
                [],
                $this->getCategory()
            )
        );
        DynamicProductGroupService::getInstance(
            $this->getContainer(),
            $this->getContainer()->get('serializer.mapping.cache.symfony'),
            Context::createDefaultContext(),
            'ABCDABCDABCDABCDABCDABCDABCDABCD',
            0
        );

        $this->loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        parent::setUp();
    }

    public function testProductInvalidExceptionIsLogged(): void
    {
        $productEntity = $this->createTestProduct();

        $expectedMessage = sprintf(
            'Product "%s" with id %s was not exported because it has no categories assigned',
            $productEntity->getTranslation('name'),
            $productEntity->getId()
        );

        $this->expectAdapterException(
            $productEntity,
            new ProductHasNoCategoriesException($productEntity),
            $expectedMessage
        );
    }

    public function testEmptyValueIsNotAllowedExceptionIsLogged(): void
    {
        $productEntity = $this->createTestProduct();

        $error = sprintf(
            'Product "%s" with id "%s" could not be exported.',
            $productEntity->getTranslation('name'),
            $productEntity->getId()
        );
        $reason = 'It appears to have empty values assigned to it.';
        $help = 'If you see this message in your logs, please report this as a bug.';
        $expectedMessage = implode(' ', [$error, $reason, $help]);

        $this->expectAdapterException(
            $productEntity,
            new EmptyValueNotAllowedException(''),
            $expectedMessage
        );
    }

    public function testThrowableExceptionIsLogged(): void
    {
        $errorMessage = 'This product failed, because it is faulty.';
        $productEntity = $this->createTestProduct();

        $error = sprintf(
            'Error while exporting the product "%s" with id "%s".',
            $productEntity->getTranslation('name'),
            $productEntity->getId()
        );
        $help = 'If you see this message in your logs, please report this as a bug.';
        $reason = sprintf('Error message: %s', $errorMessage);
        $expectedMessage = implode(' ', [$error, $help, $reason]);

        $this->expectAdapterException(
            $productEntity,
            new Exception($errorMessage),
            $expectedMessage
        );
    }

    private function getExportItemAdapter(AdapterFactory $adapterFactory): ExportItemAdapter
    {
        return new ExportItemAdapter(
            $this->getContainer()->get('service_container'),
            $this->getContainer()->get('router'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get('FINDOLOGIC\FinSearch\Struct\Config'),
            $adapterFactory,
            $this->getContainer()->get('fin_search.export_context'),
            $this->loggerMock
        );
    }

    public function expectAdapterException(ProductEntity $productEntity, Throwable $throwable, string $message): void
    {
        $nameAdapterMock = $this->getMockBuilder(NameAdapter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $nameAdapterMock->expects($this->once())
            ->method('adapt')
            ->willThrowException($throwable);

        $adapterFactoryMock = $this->getMockBuilder(AdapterFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $adapterFactoryMock->expects($this->once())
            ->method('getNameAdapter')
            ->willReturn($nameAdapterMock);

        $this->loggerMock->expects($this->exactly(1))
            ->method('warning')
            ->with($message);

        $adapter = $this->getExportItemAdapter($adapterFactoryMock);
        $adapter->adapt(new XMLItem('123'), $productEntity);
    }

    public function getCategory(): CategoryEntity
    {
        $categoryRepo = $this->getContainer()->get('category.repository');
        $categories = $categoryRepo->search(new Criteria(), Context::createDefaultContext());

        /** @var CategoryEntity $expectedCategory */
        return $categories->first();
    }
}
