<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Description;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;

class DescriptionAdapter
{
    public function adapt(ProductEntity $product): Description
    {
        $description = new Description();
        $description->setValue($this->getDescription($product));

        return $description;
    }

    private function getDescription(ProductEntity $product): string
    {
        $description = $product->getTranslation('description');

        if (Utils::isEmpty($description)) {
            return '';
        }

        return $description;
    }
}
