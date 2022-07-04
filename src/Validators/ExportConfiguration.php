<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Validators;

use Symfony\Component\Validator\Constraints as Assert;

class ExportConfiguration extends ExportConfigurationBase
{
    public const DEFAULT_START_PARAM = 0;
    public const DEFAULT_COUNT_PARAM = 20;

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
     * @Assert\Type(
     *     type="integer",
     *     message="The value {{ value }} is not a valid {{ type }}."
     * )
     * @Assert\GreaterThanOrEqual(0)
     * @var int
     */
    private $start;

    /**
     * @Assert\NotBlank
     * @Assert\Type(
     *     type="integer",
     *     message="The value {{ value }} is not a valid {{ type }}."
     * )
     * @Assert\GreaterThan(0)
     * @var int
     */
    private $count;

    /**
     * @Assert\Type("string")
     * @var string|null
     */
    private $productId;

    public function __construct(string $shopkey, int $start, int $count, ?string $productId = null)
    {
        $this->shopkey = $shopkey;
        $this->start = $start;
        $this->count = $count;
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

    public function getProductId(): ?string
    {
        return $this->productId;
    }
}
