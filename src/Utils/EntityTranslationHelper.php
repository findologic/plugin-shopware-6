<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Utils;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\Context;

class EntityTranslationHelper
{
    /** @var Context */
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    public function getPropertyGroupOptionTranslations(
        PropertyGroupOptionEntity $propertyGroupOptionEntity
    ): PropertyGroupOptionTranslationEntity {
        foreach ($propertyGroupOptionEntity->getTranslations() as $translationEntity) {
            if ($this->context->getLanguageId() === $translationEntity->getLanguageId()) {
                return $translationEntity;
            }
        }

        $propertyGroupOptionEntity->getTranslations()->first();
    }

    public function getPropertyGroupTranslations(
        PropertyGroupEntity $propertyGroupEntity
    ): PropertyGroupTranslationEntity {
        foreach ($propertyGroupEntity->getTranslations() as $translationEntity) {
            if ($this->context->getLanguageId() === $translationEntity->getLanguageId()) {
                return $translationEntity;
            }
        }

        $propertyGroupEntity->getTranslations()->first();
    }

    public function getManufacturerTranslations(
        ProductManufacturerEntity $manufacturerEntity
    ): ProductManufacturerTranslationEntity {
        foreach ($manufacturerEntity->getTranslations() as $translationEntity) {
            if ($this->context->getLanguageId() === $translationEntity->getLanguageId()) {
                return $translationEntity;
            }
        }

        return $manufacturerEntity->getTranslations()->first();
    }

    public function getProductTranslations(ProductEntity $productEntity): ProductTranslationEntity
    {
        /** @var ProductTranslationEntity $translationEntity */
        foreach ($productEntity->getTranslations() as $translationEntity) {
            if ($this->context->getLanguageId() === $translationEntity->getLanguageId()) {
                return $translationEntity;
            }
        }

        return $productEntity->getTranslations()->first();
    }
}
