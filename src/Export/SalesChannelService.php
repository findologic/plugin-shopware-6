<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Exceptions\Export\UnknownShopkeyException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;

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

        /** @var SystemConfigEntity $systemConfigEntity */
        foreach ($systemConfigEntities as $systemConfigEntity) {
            if ($systemConfigEntity->getConfigurationValue() === $shopkey) {
                // When the configured sales channel id is null, the shopkey is configured for all sales channels.
                if ($systemConfigEntity->getSalesChannelId() === null) {
                    return $currentContext;
                }

                return $this->salesChannelContextFactory->create(
                    $currentContext->getToken(),
                    $systemConfigEntity->getSalesChannelId()
                );
            }
        }

        return null;
    }
}
