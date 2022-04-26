<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Adapters;

use FINDOLOGIC\Export\Data\Name;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;

class NameAdapter
{
    /**
     * @throws ProductHasNoNameException
     */
    public function adapt(ProductEntity $product): ?Name
    {
        $value = new Name();
        $value->setValue($this->getCleanedName($product));

        return $value;
    }

    /**
     * @throws ProductHasNoNameException
     */
    protected function getCleanedName(ProductEntity $product): string
    {
        $name = $product->getTranslation('name');

        if (Utils::isEmpty($name)) {
            throw new ProductHasNoNameException($product);
        }

        return Utils::removeControlCharacters($name);
    }
}
