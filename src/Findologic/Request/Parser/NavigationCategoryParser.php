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
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class NavigationCategoryParser
{
    /** @var ContainerInterface */
    private $container;

    /** @var GenericPageLoader */
    private $genericPageLoader;

    public function __construct(ContainerInterface $container, GenericPageLoader $genericPageLoader)
    {
        $this->container = $container;
        $this->genericPageLoader = $genericPageLoader;
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
        $page = $this->genericPageLoader->load($request, $salesChannelContext);
        $navPage = NavigationPage::createFrom($page);

        if ($page->getHeader()) {
            return $this->parseFromHeader($navPage->getHeader());
        } elseif ($request->get('navigationId')) {
            return $this->parseFromRequest($request, $salesChannelContext);
        }
        // Parsing the category from somewhere else is not possible.
        return null;
    }

    private function parseFromHeader(HeaderPagelet $header): CategoryEntity
    {
        return $header->getNavigation()->getActive();
    }

    private function parseFromRequest(Request $request, SalesChannelContext $salesChannelContext): CategoryEntity
    {
        $navigationId = $request->get('navigationId');

        /** @var EntityRepository $categoryRepository */
        $categoryRepository = $this->container->get('category.repository');

        $categories = $categoryRepository->search(
            new Criteria([$navigationId]),
            $salesChannelContext->getContext()
        );

        return $categories->get($navigationId);
    }
}
