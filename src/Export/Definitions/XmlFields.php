<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Definitions;

class XmlFields
{
    /**
     * Products can contain these fields, if some are not set, the default will be used instead.
     */
    public const FIELDS = [
        'name',
        'attributes',
        'prices',
        'description',
        'dateAdded',
        'url',
        'keywords',
        'images',
        'salesFrequency',
        'userGroups',
        'ordernumbers',
        'properties',
    ];
}
