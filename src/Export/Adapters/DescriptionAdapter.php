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
        $value = new Description();
        $description = $this->getDescription($product);

        if (!$description) {
            return null;
        }

        $value->setValue($description);

        return $value;
    }

    private function getDescription(ProductEntity $product): ?string
    {
        $description = $product->getTranslation('description');

        if (Utils::isEmpty($description)) {
            return null;
        }

        return $description;
    }
}
