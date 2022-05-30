<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Validators;

use Symfony\Component\HttpFoundation\Request;

abstract class ExportConfigurationBase
{
    private $shopkey;

    private $productId;

    private $start;

    private $count;

    abstract public static function getInstance(Request $request);

    abstract public function getShopkey(): string;
    abstract public function getStart(): int;
    abstract public function getCount(): int;
    abstract public function getProductId(): ?string;
}
