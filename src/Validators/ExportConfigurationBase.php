<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Validators;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

abstract class ExportConfigurationBase
{
    private string $shopkey;

    private ?string $productId;

    private int $start;

    private int $count;

    public static function getInstance(Request $request): ExportConfigurationBase
    {
        switch ($request->getPathInfo()) {
            case '/findologic':
                return new ExportConfiguration(
                    $request->query->get('shopkey', ''),
                    $request->query->getInt('start', ExportConfiguration::DEFAULT_START_PARAM),
                    $request->query->getInt('count', ExportConfiguration::DEFAULT_COUNT_PARAM),
                    $request->query->get('productId')
                );
            case '/findologic/debug':
                return new DebugExportConfiguration(
                    $request->query->get('shopkey', ''),
                    $request->query->get('productId', '')
                );
            default:
                throw new InvalidArgumentException(
                    sprintf('Unknown export configuration type for path %d.', $request->getPathInfo())
                );
        }
    }

    abstract public function getShopkey(): string;
    abstract public function getStart(): int;
    abstract public function getCount(): int;
    abstract public function getProductId(): ?string;
}
