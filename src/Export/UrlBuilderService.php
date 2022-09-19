<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\Shopware6Common\Export\ExportContext;
use FINDOLOGIC\Shopware6Common\Export\Services\AbstractUrlBuilderService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

class UrlBuilderService extends AbstractUrlBuilderService
{
    private SalesChannelContext $salesChannelContext;

    private EntityRepository $categoryRepository;

    public function __construct(
        SalesChannelContext $salesChannelContext,
        EntityRepository $categoryRepository,
        RouterInterface $router,
        ExportContext $exportContext
    ) {
        $this->salesChannelContext = $salesChannelContext;
        $this->categoryRepository = $categoryRepository;

        parent::__construct($router, $exportContext);
    }

    protected function fetchParentsFromCategoryPath(string $categoryPath): ?array
    {
        $parentIds = array_filter(explode('|', $categoryPath));
        $criteria = new Criteria($parentIds);
        $criteria->addAssociation('seoUrls');

        return $this->categoryRepository
            ->search($criteria, $this->salesChannelContext->getContext())
            ->getElements();
    }
}
