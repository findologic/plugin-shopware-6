<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Findologic\Config\FinSearchConfigEntity;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelService
{
    /** @var EntityRepository */
    private $systemConfigRepository;

    /** @var SalesChannelContextFactory|AbstractSalesChannelContextFactory */
    private $salesChannelContextFactory;

    /** @var RequestTransformerInterface */
    private $requestTransformer;

    /**
     * @param SalesChannelContextFactory|AbstractSalesChannelContextFactory $salesChannelContextFactory
     */
    public function __construct(
        EntityRepository $systemConfigRepository,
        $salesChannelContextFactory,
        RequestTransformerInterface $requestTransformer
    ) {
        $this->systemConfigRepository = $systemConfigRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
        $this->requestTransformer = $requestTransformer;
    }

    /**
     * Returns the sales channel assigned to the given shopkey. Returns null in case the given shopkey is not
     * assigned to any sales channel.
     */
    public function getSalesChannelContext(
        SalesChannelContext $currentContext,
        string $shopkey
    ): ?SalesChannelContext {
        $systemConfigEntities = $this->systemConfigRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('configurationKey', 'FinSearch.config.shopkey')),
            $currentContext->getContext()
        );

        /** @var FinSearchConfigEntity $systemConfigEntity */
        foreach ($systemConfigEntities as $systemConfigEntity) {
            if ($systemConfigEntity->getConfigurationValue() === $shopkey) {
                return $this->salesChannelContextFactory->create(
                    $currentContext->getToken(),
                    $systemConfigEntity->getSalesChannelId(),
                    [SalesChannelContextService::LANGUAGE_ID => $systemConfigEntity->getLanguageId()]
                );
            }
        }

        return null;
    }

    /**
     * Builds a new Request instance based on the given SalesChannelContext. The new instance is built
     * with the Shopware RequestTransformer, which is automatically called for all Controllers.
     */
    public function getRequest(Request $originalRequest, SalesChannelContext $salesChannelContext): Request
    {
        $domain = $this->getSalesChannelDomain($salesChannelContext);

        $parsedUrl = parse_url($domain->getUrl());

        // There is no Request::setUrl(), therefore we need to duplicate the current request object.
        // @see https://github.com/symfony/symfony/issues/14575#issuecomment-102942494
        $request = $originalRequest->duplicate(
            null,
            null,
            null,
            null,
            null,
            array_merge($originalRequest->server->all(), [
                'REQUEST_URI' => $parsedUrl['path'] ?? '/',
                'HTTP_HOST' => $parsedUrl['host'] . (isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : ''),
            ])
        );

        return $this->requestTransformer->transform($request);
    }

    private function getSalesChannelDomain(SalesChannelContext $salesChannelContext): ?SalesChannelDomainEntity
    {
        $languageId = $salesChannelContext->getSalesChannel()->getLanguageId();

        /** @var SalesChannelDomainCollection $domains */
        $domains = $salesChannelContext->getSalesChannel()->getDomains()->filterByProperty(
            'languageId',
            $languageId
        );

        return Utils::filterSalesChannelDomainsWithoutHeadlessDomain($domains)
            ->first();
    }
}
