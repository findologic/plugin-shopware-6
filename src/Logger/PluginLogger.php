<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Logger;

use FINDOLOGIC\FinSearch\Exceptions\Export\Product\AccessEmptyPropertyException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasCrossSellingCategoryException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoAttributesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoCategoriesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoNameException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductHasNoPricesException;
use FINDOLOGIC\FinSearch\Exceptions\Export\Product\ProductInvalidException;
use Monolog\Logger;

class PluginLogger extends Logger
{
    public function logProductInvalidException(ProductInvalidException $e): void {
        switch (get_class($e)) {
            case AccessEmptyPropertyException::class:
                $message = sprintf(
                    'Product with id %s was not exported because the property does not exist',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoAttributesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no attributes',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoNameException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no name set',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoPricesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no price associated to it',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasNoCategoriesException::class:
                $message = sprintf(
                    'Product with id %s was not exported because it has no categories assigned',
                    $e->getProduct()->getId()
                );
                break;
            case ProductHasCrossSellingCategoryException::class:
                $message = sprintf(
                    'Product with id %s (%s) was not exported because it ' .
                    'is assigned to cross selling category %s (%s)',
                    $e->getProduct()->getId(),
                    $e->getProduct()->getName(),
                    $e->getCategory()->getId(),
                    implode(' > ', $e->getCategory()->getBreadcrumb())
                );
                break;
            default:
                $message = sprintf(
                    'Product with id %s could not be exported.',
                    $e->getProduct()->getId()
                );
        }

        $this->warning($message, ['exception' => $e]);
    }
}
