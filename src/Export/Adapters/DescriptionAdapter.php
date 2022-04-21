<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Description;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;

class DescriptionAdapter
{
    public function adapt(ProductEntity $product): ?Description
    {
        if (!$descriptionValue = $this->getDescription($product)) {
            return null;
        }

        $description = new Description();
        $description->setValue($descriptionValue);

        return $description;
    }

    protected function getDescription(ProductEntity $product): ?string
    {
        $description = $product->getTranslation('description');

        if (Utils::isEmpty($description)) {
            return null;
        }

        return $description;
    }
}
