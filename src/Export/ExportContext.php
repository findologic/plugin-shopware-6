<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Content\Category\CategoryEntity;

class ExportContext
{
    protected string $shopkey;

    /** @var CustomerGroupEntity[] */
    protected array $customerGroups;

    protected ?CategoryEntity $navigationRootCategory;

    public function __construct(
        string $shopkey,
        array $customerGroups = [],
        ?CategoryEntity $navigationRootCategory = null
    ) {
        $this->shopkey = $shopkey;
        $this->customerGroups = $customerGroups;
        $this->navigationRootCategory = $navigationRootCategory;
    }

    public function setShopkey(string $shopkey): void
    {
        $this->shopkey = $shopkey;
    }

    public function getShopkey(): string
    {
        return $this->shopkey;
    }

    /**
     * @param CustomerGroupEntity[] $customerGroups
     */
    public function setCustomerGroups(array $customerGroups): void
    {
        $this->customerGroups = $customerGroups;
    }

    /**
     * @return CustomerGroupEntity[]
     */
    public function getCustomerGroups(): array
    {
        return $this->customerGroups;
    }

    public function setNavigationRootCategory(?CategoryEntity $navigationRootCategory): void
    {
        $this->navigationRootCategory = $navigationRootCategory;
    }

    public function getNavigationRootCategory(): ?CategoryEntity
    {
        return $this->navigationRootCategory;
    }
}
