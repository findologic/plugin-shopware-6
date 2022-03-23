<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

trait CategoryHelper
{
    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function createTestCategory(?array $overrideData = []): CategoryEntity
    {
        $defaults = [
            'id' => Uuid::randomHex(),
            'name' => Uuid::randomHex(),
            'active' => true,
        ];
        $categories = [
            array_merge($defaults, $overrideData)
        ];

        $repository = $this->getContainer()->get('category.repository');

        $repository->create($categories, Context::createDefaultContext());

        $criteria = new Criteria([$categories[0]['id']]);
        $criteria->addAssociation('children');

        /** @var CategoryCollection $result */
        $result = $repository->search($criteria, Context::createDefaultContext());

        return $result->first();
    }
}
