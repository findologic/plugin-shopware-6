<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Config;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                       add(FinSearchConfigEntity $entity)
 * @method void                       set(string $key, FinSearchConfigEntity $entity)
 * @method FinSearchConfigEntity[]    getIterator()
 * @method FinSearchConfigEntity[]    getElements()
 * @method FinSearchConfigEntity|null get(string $key)
 * @method FinSearchConfigEntity|null first()
 * @method FinSearchConfigEntity|null last()
 */
class FinSearchConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'finsearch_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return FinSearchConfigEntity::class;
    }
}
