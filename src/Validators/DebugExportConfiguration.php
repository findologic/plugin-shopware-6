<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Validators;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

class DebugExportConfiguration extends ExportConfigurationBase
{
    /**
     * @Assert\NotBlank
     * @Assert\Regex(
     *     pattern="/^[A-F0-9]{32}$/",
     *     message="Invalid key provided."
     * )
     */
    private $shopkey;

    /**
     * @Assert\NotBlank
     * @Assert\Uuid(
     *     strict=false
     *)
     * @var string
     */
    private $productId;

    private $start = 0;

    private $count = 1;

    public function __construct(string $shopkey, string $productId)
    {
        $this->shopkey = $shopkey;
        $this->productId = $productId;
    }

    public function getShopkey(): string
    {
        return $this->shopkey;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }
}
