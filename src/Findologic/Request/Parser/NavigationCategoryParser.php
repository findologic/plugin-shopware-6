<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Request\Parser;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationCategoryParser
{
    private ContainerInterface $container;

    public function __construct(
        ContainerInterface $container
    ) {
        $this->container = $container;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function parse(
        Request $request,
        SalesChannelContext $salesChannelContext
    ): ?CategoryEntity {
        return $this->parseFromRequest($request, $salesChannelContext);
    }

    private function parseFromRequest(Request $request, SalesChannelContext $salesChannelContext): ?CategoryEntity
    {
        $navigationId = $request->get(
            'navigationId',
            $salesChannelContext->getSalesChannel()->getNavigationCategoryId()
        );

        if (!$navigationId) {
            return null;
        }

        /** @var EntityRepository $categoryRepository */
        $categoryRepository = $this->container->get('category.repository');

        $categories = $categoryRepository->search(
            new Criteria([$navigationId]),
            $salesChannelContext->getContext()
        );

        return $categories->get($navigationId);
    }
}
