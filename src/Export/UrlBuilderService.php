<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\RouterInterface;

use const PHP_URL_PATH;

class UrlBuilderService
{
    /** @var SalesChannelContext */
    private $salesChannelContext;

    /** @var RouterInterface */
    private $router;

    /** @var EntityRepository */
    private $categoryRepository;

    public function __construct(RouterInterface $router, EntityRepository $categoryRepository)
    {
        $this->router = $router;
        $this->categoryRepository = $categoryRepository;
    }

    public function setSalesChannelContext(SalesChannelContext $salesChannelContext): void
    {
        $this->salesChannelContext = $salesChannelContext;
    }

    /**
     * Builds the URL of the given product for the currently used language. Automatically fallbacks to
     * generating a URL via the router in case the product does not have a SEO URL configured.
     * E.g.
     * * http://localhost:8000/Lightweight-Paper-Prior-IT/7562a1140f7f4abd8c6a4a4b6d050b77
     * * https://your-shop.com/detail/032c79962b3f4fb4bd1e9117005b42c1
     * * https://your-shop.com/de/Cooles-Produkt/c0421a8d8af840ecad60971ec5280476
     */
    public function buildProductUrl(ProductEntity $product): string
    {
        $seoPath = $this->getProductSeoPath($product);
        if (!$seoPath) {
            return $this->getFallbackUrl($product);
        }

        $domain = $this->getSalesChannelDomain();
        if (!$domain) {
            return $this->getFallbackUrl($product);
        }

        return $this->buildSeoUrl($domain, $seoPath);
    }

    /**
     * Builds `cat_url`s for Direct Integrations. Based on the given category, all
     * paths excluding the root category are generated.
     * E.g. Category Structure "Something > Root Category > Men > Shirts > T-Shirts" exports
     * * /Men/Shirts/T-Shirts/
     * * /Men/Shirts/
     * * /Men/
     * * /navigation/4e43b925d5ec43339d2b3414a91151ab
     * In case there is a language prefix assigned to the Sales Channel, this would also be included.
     * E.g.
     * * /de/Men/Shirts/T-Shirts/
     * * /de/navigation/4e43b925d5ec43339d2b3414a91151ab
     *
     * @return string[]
     */
    public function getCategoryUrls(CategoryEntity $category, Context $context): array
    {
        $categories = $this->getParentCategories($category, $context);
        $categoryUrls = [];

        $categoryUrls[] = $this->buildCategoryUrls($category);
        foreach ($categories as $categoryEntity) {
            $categoryUrls[] = $this->buildCategoryUrls($categoryEntity);
        }

        return $categoryUrls;
    }

    /**
     * Gets the domain of the sales channel for the currently used language. Suffixed slashes are removed.
     * E.g.
     * * http://localhost:8000
     * * https://your-domain.com
     * * https://your-domain.com/de
     *
     * @return string|null
     */
    protected function getSalesChannelDomain(): ?string
    {
        $allDomains = $this->salesChannelContext->getSalesChannel()->getDomains();
        $allDomains = Utils::filterSalesChannelDomainsWithoutHeadlessDomain($allDomains);
        $domains = $this->getTranslatedEntities($allDomains);

        if (!$domains || !$domains->first()) {
            return null;
        }

        return rtrim($domains->first()->getUrl(), '/');
    }

    /**
     * Gets the SEO path of the given product for the currently used language. Prefixed slashes are removed.
     * E.g.
     * * Lightweight-Paper-Prior-IT/7562a1140f7f4abd8c6a4a4b6d050b77
     * * Sony-Alpha-7-III-Sigma-AF-24-70mm-1-2-8-DG-DN-ART/145055000510
     */
    protected function getProductSeoPath(ProductEntity $product): ?string
    {
        $allSeoUrls = $product->getSeoUrls();
        if (!$allSeoUrls) {
            return null;
        }

        $applicableSeoUrls = $this->getApplicableSeoUrls($allSeoUrls);
        $seoUrls = $this->getTranslatedEntities($applicableSeoUrls);
        if (!$seoUrls || !$seoUrls->first()) {
            return null;
        }

        $canonicalSeoUrl = $seoUrls->filter(function (SeoUrlEntity $entity) {
            return $entity->getIsCanonical();
        })->first();
        $seoUrl = $canonicalSeoUrl ?? $canonicalSeoUrl->first();

        return ltrim($seoUrl->getSeoPathInfo(), '/');
    }

    /**
     * Filters the given collection to only return entities for the current language.
     */
    protected function getTranslatedEntities(?EntityCollection $collection): ?Collection
    {
        if (!$collection) {
            return null;
        }

        $translatedEntities = $collection->filterByProperty(
            'languageId',
            $this->salesChannelContext->getSalesChannel()->getLanguageId()
        );

        if ($translatedEntities->count() === 0) {
            return null;
        }

        return $translatedEntities;
    }

    /**
     * Filters out non-applicable SEO URLs based on the current context.
     */
    protected function getApplicableSeoUrls(SeoUrlCollection $collection): SeoUrlCollection
    {
        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();

        return $collection->filter(static function (SeoUrlEntity $seoUrl) use ($salesChannelId) {
            return $seoUrl->getSalesChannelId() === $salesChannelId && !$seoUrl->getIsDeleted();
        });
    }

    protected function getFallbackUrl(ProductEntity $product): string
    {
        return $this->router->generate(
            'frontend.detail.page',
            ['productId' => $product->getId()],
            RouterInterface::ABSOLUTE_URL
        );
    }

    protected function buildSeoUrl(string $domain, string $seoPath): string
    {
        return sprintf('%s/%s', $domain, $seoPath);
    }

    /**
     * Returns all parent categories of the given category.
     * The main navigation category (aka. root category) won't be added to the array.
     *
     * @return CategoryEntity[]
     */
    public function getParentCategories(CategoryEntity $category, Context $context): array
    {
        $parentCategories = $this->fetchParentsFromCategoryPath($category, $context);
        $categories = [];

        /** @var CategoryEntity $categoryInPath */
        foreach ($parentCategories as $categoryInPath) {
            if ($categoryInPath->getId() === $this->salesChannelContext->getSalesChannel()->getNavigationCategoryId()) {
                continue;
            }

            $categories[] = $categoryInPath;
        }

        return $categories;
    }

    protected function fetchCategorySeoUrls(CategoryEntity $categoryEntity): SeoUrlCollection
    {
        $seoUrls = new SeoUrlCollection();
        if ($categoryEntity->getSeoUrls() && $categoryEntity->getSeoUrls()->count() > 0) {
            $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();
            foreach ($categoryEntity->getSeoUrls()->getElements() as $seoUrlEntity) {
                $seoUrlSalesChannelId = $seoUrlEntity->getSalesChannelId();
                if ($seoUrlSalesChannelId === $salesChannelId || $seoUrlSalesChannelId === null) {
                    $seoUrls->add($seoUrlEntity);
                }
            }
        }

        return $seoUrls;
    }

    /**
     * Returns all SEO paths for the given category.
     *
     * @return string[]
     */
    protected function buildCategorySeoUrl(CategoryEntity $categoryEntity): array
    {
        $salesChannelId = $this->salesChannelContext->getSalesChannel()->getId();
        $allSeoUrls = $this->fetchCategorySeoUrls($categoryEntity);
        $salesChannelSeoUrls = $allSeoUrls->filterBySalesChannelId($salesChannelId);
        if ($salesChannelSeoUrls->count() === 0) {
            return [];
        }

        $seoUrls = [];
        foreach ($salesChannelSeoUrls as $seoUrl) {
            $pathInfo = $seoUrl->getSeoPathInfo();
            if (Utils::isEmpty($pathInfo)) {
                continue;
            }

            $seoUrls[] = $this->getCatUrlPrefix() . sprintf('/%s', ltrim($pathInfo, '/'));
        }

        return $seoUrls;
    }

    protected function filterDeletedSeoUrls(SeoUrlCollection $collection): SeoUrlCollection
    {
        return $collection->filter(function (SeoUrlEntity $entity) {
            return !$entity->getIsDeleted();
        });
    }

    protected function getCatUrlPrefix(): string
    {
        $url = $this->getTranslatedDomainBaseUrl();
        if (!$url) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return '';
        }

        return rtrim($path, '/');
    }

    protected function getTranslatedDomainBaseUrl(): ?string
    {
        $salesChannel = $this->salesChannelContext->getSalesChannel();
        $domainCollection = Utils::filterSalesChannelDomainsWithoutHeadlessDomain($salesChannel->getDomains());
        $domainEntities = $this->getTranslatedEntities($domainCollection);

        return $domainEntities && $domainEntities->first() ? rtrim($domainEntities->first()->getUrl(), '/') : null;
    }

    protected function buildCategoryUrls(CategoryEntity $category): array
    {
        $categoryUrls = $this->buildCategorySeoUrl($category);
        $categoryUrls[] = sprintf(
            '/%s',
            ltrim(
                $this->router->generate(
                    'frontend.navigation.page',
                    ['navigationId' => $category->getId()],
                    RouterInterface::ABSOLUTE_PATH
                ),
                '/'
            )
        );

        return $categoryUrls;
    }

    protected function fetchParentsFromCategoryPath(CategoryEntity $category, Context $context): ?CategoryCollection
    {
        $path = $category->getPath();
        if (!$path) {
            return null;
        }

        $parentIds = array_filter(explode('|', $path));
        $criteria = new Criteria($parentIds);
        $criteria->addAssociation('seoUrls');

        return $this->categoryRepository->search($criteria, $context)->getEntities();
    }
}
