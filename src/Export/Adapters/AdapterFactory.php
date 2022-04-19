<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

class AdapterFactory
{
    /** @var NameAdapter */
    private $nameAdapter;

    /** @var AttributeAdapter */
    private $attributeAdapter;

    /** @var PriceAdapter */
    private $priceAdapter;

    /** @var UrlAdapter */
    private $urlAdapter;

    /** @var DescriptionAdapter */
    private $descriptionAdapter;

    /** @var DateAddedAdapter $dateAddedAdapter */
    private $dateAddedAdapter;

    /** @var KeywordsAdapter $keywordsAdapter */
    private $keywordsAdapter;

    /** @var ImagesAdapter $imagesAdapter */
    private $imagesAdapter;

    /** @var SalesFrequencyAdapter $salesFrequencyAdapter */
    private $salesFrequencyAdapter;

    /** @var UserGroupsAdapter $userGroupsAdapter */
    private $userGroupsAdapter;

    /** @var OrderNumberAdapter $orderNumberAdapter */
    private $orderNumberAdapter;

    /** @var PropertiesAdapter $propertiesAdapter */
    private $propertiesAdapter;

    public function __construct(
        NameAdapter $itemNameAdapter,
        AttributeAdapter $attributeAdapter,
        PriceAdapter $priceAdapter,
        UrlAdapter $urlAdapter,
        DescriptionAdapter $descriptionAdapter,
        DateAddedAdapter $dateAddedAdapter,
        KeywordsAdapter $keywordsAdapter,
        ImagesAdapter $imagesAdapter,
        SalesFrequencyAdapter $salesFrequencyAdapter,
        UserGroupsAdapter $userGroupsAdapter,
        OrderNumberAdapter $orderNumberAdapter,
        PropertiesAdapter $propertiesAdapter
    ) {
        $this->nameAdapter = $itemNameAdapter;
        $this->attributeAdapter = $attributeAdapter;
        $this->priceAdapter = $priceAdapter;
        $this->urlAdapter = $urlAdapter;
        $this->descriptionAdapter = $descriptionAdapter;
        $this->dateAddedAdapter = $dateAddedAdapter;
        $this->keywordsAdapter = $keywordsAdapter;
        $this->imagesAdapter = $imagesAdapter;
        $this->salesFrequencyAdapter = $salesFrequencyAdapter;
        $this->userGroupsAdapter = $userGroupsAdapter;
        $this->orderNumberAdapter = $orderNumberAdapter;
        $this->propertiesAdapter = $propertiesAdapter;
    }

    public function getNameAdapter(): NameAdapter
    {
        return $this->nameAdapter;
    }

    public function getAttributeAdapter(): AttributeAdapter
    {
        return $this->attributeAdapter;
    }

    public function getPriceAdapter(): PriceAdapter
    {
        return $this->priceAdapter;
    }

    public function getUrlAdapter(): UrlAdapter
    {
        return $this->urlAdapter;
    }

    public function getDescriptionAdapter(): DescriptionAdapter
    {
        return $this->descriptionAdapter;
    }

    public function getDateAddedAdapter(): DateAddedAdapter
    {
        return $this->dateAddedAdapter;
    }

    public function getKeywordsAdapter(): KeywordsAdapter
    {
        return $this->keywordsAdapter;
    }

    public function getImagesAdapter(): ImagesAdapter
    {
        return $this->imagesAdapter;
    }

    public function getSalesFrequencyAdapter(): SalesFrequencyAdapter
    {
        return $this->salesFrequencyAdapter;
    }

    public function getUserGroupsAdapter(): UserGroupsAdapter
    {
        return $this->userGroupsAdapter;
    }

    public function getOrderNumbersAdapter(): OrderNumberAdapter
    {
        return $this->orderNumberAdapter;
    }

    public function getPropertiesAdapter(): PropertiesAdapter
    {
        return $this->propertiesAdapter;
    }
}
