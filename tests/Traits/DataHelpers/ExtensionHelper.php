<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Tests\Traits\DataHelpers;

use FINDOLOGIC\FinSearch\Struct\SmartDidYouMean;

trait ExtensionHelper
{
    public function getDefaultSmartDidYouMeanExtension(
        ?string $originalQuery = 'ps4',
        ?string $effectiveQuery = 'ps4',
        ?string $correctedQuery = '',
        ?string $didYouMeanQuery = '',
        ?string $improvedQuery = '',
        ?string $controllerPath = ''
    ): SmartDidYouMean {
        return new SmartDidYouMean(
            $originalQuery,
            $effectiveQuery,
            $correctedQuery,
            $didYouMeanQuery,
            $improvedQuery,
            $controllerPath
        );
    }
}
