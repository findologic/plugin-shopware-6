<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Findologic\Config\FinSearchConfigEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelService
{
    /** @var EntityRepository */
    private $systemConfigRepository;

    /** @var SalesChannelContextFactory */
    private $salesChannelContextFactory;

    public function __construct(
        EntityRepository $systemConfigRepository,
        SalesChannelContextFactory $salesChannelContextFactory
    ) {
        $this->systemConfigRepository = $systemConfigRepository;
        $this->salesChannelContextFactory = $salesChannelContextFactory;
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
}
