<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

class AdapterFactory
{
    /** @var NameAdapter */
    protected $nameAdapter;

    /** @var AttributeAdapter */
    protected $attributeAdapter;

    /** @var PriceAdapter */
    protected $priceAdapter;

    /** @var UrlAdapter */
    protected $urlAdapter;

    public function __construct(
        NameAdapter $itemNameAdapter,
        AttributeAdapter $attributeAdapter,
        PriceAdapter $priceAdapter,
        UrlAdapter $urlAdapter
    ) {
        $this->nameAdapter = $itemNameAdapter;
        $this->attributeAdapter = $attributeAdapter;
        $this->priceAdapter = $priceAdapter;
        $this->urlAdapter = $urlAdapter;
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
}
