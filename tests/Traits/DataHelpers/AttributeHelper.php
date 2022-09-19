<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\Export\Data\Attribute;
use FINDOLOGIC\Shopware6Common\Export\Utils\Utils;
use Shopware\Core\Content\Product\ProductEntity;

trait AttributeHelper
{
    /**
     * @return Attribute[]
     */
    public function getAttributes(ProductEntity $productEntity, string $integrationType = 'Direct Integration'): array
    {
        $catUrl1 = '/FINDOLOGIC-Category/';
        $defaultCatUrl = '';

        foreach ($productEntity->getCategories() as $category) {
            if ($category->getName() === 'FINDOLOGIC Category') {
                $defaultCatUrl = sprintf('/navigation/%s', $category->getId());
            }
        }

        $attributes = [];
        $catUrlAttribute = new Attribute('cat_url', [$catUrl1, $defaultCatUrl]);
        $catAttribute = new Attribute('cat', ['FINDOLOGIC Category']);
        $vendorAttribute = new Attribute('vendor', ['FINDOLOGIC']);

        if ($integrationType === 'Direct Integration') {
            $attributes[] = $catUrlAttribute;
        }

        $attributes[] = $catAttribute;
        $attributes[] = $vendorAttribute;
        $attributes[] = new Attribute(
            $productEntity->getProperties()
                ->first()
                ->getGroup()
                ->getName(),
            [
                $productEntity->getProperties()
                    ->first()
                    ->getName()
            ]
        );

        $shippingFree = $this->translateBooleanValue($productEntity->getShippingFree());
        $attributes[] = new Attribute('shipping_free', [$shippingFree]);

        $rating = $productEntity->getRatingAverage() ?? 0.0;
        $attributes[] = new Attribute('rating', [$rating]);

        // Custom fields as attributes
        $productFields = $productEntity->getCustomFields();
        if ($productFields) {
            foreach ($productFields as $key => $value) {
                if (is_bool($value)) {
                    $value = $this->translateBooleanValue($value);
                }
                $attributes[] = new Attribute(Utils::removeSpecialChars($key), [$value]);
            }
        }

        return $attributes;
    }

    private function translateBooleanValue(bool $value): string
    {
        $translationKey = $value ? 'finSearch.general.yes' : 'finSearch.general.no';

        return $this->getContainer()->get('translator')->trans($translationKey);
    }
}
