<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;

trait ExtensionHelper
{
    public function getDefaultSmartDidYouMeanExtension(
        ?string $originalQuery = 'ps4',
        ?string $alternativeQuery = 'ps4',
        ?string $didYouMeanQuery = 'ps4',
        ?string $type = 'did-you-mean',
        ?string $controllerPath = ''
    ): SmartDidYouMean {
        return new SmartDidYouMean(
            $originalQuery,
            $alternativeQuery,
            $didYouMeanQuery,
            $type,
            $controllerPath
        );
    }
}
