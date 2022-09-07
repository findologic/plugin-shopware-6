<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Validators;

use Symfony\Component\Validator\Constraints as Assert;

class DynamicProductGroupsConfiguration extends ExportConfigurationBase
{
    public const DEFAULT_COUNT_PARAM = 20;

    /**
     * @Assert\NotBlank
     * @Assert\Regex(
     *     pattern="/^[A-F0-9]{32}$/",
     *     message="Invalid key provided."
     * )
     */
    private string $shopkey;

    /**
     * @Assert\NotBlank
     * @Assert\Type(
     *     type="integer",
     *     message="The value {{ value }} is not a valid {{ type }}."
     * )
     * @Assert\GreaterThanOrEqual(0)
     */
    private int $start;

    /**
     * @Assert\Type("string")
     */
    private ?string $productId;

    public function __construct(string $shopkey, int $start, int $count, ?string $productId = null)
    {
        $this->shopkey = $shopkey;
        $this->start = $start;
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
        return self::DEFAULT_COUNT_PARAM;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }
}
