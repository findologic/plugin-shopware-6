<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Validators;

use Symfony\Component\Validator\Constraints as Assert;

class ExportConfiguration
{
    /**
     * @Assert\NotBlank
     * @Assert\Regex("/^[A-F0-9]{32}$/")
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

    public function getShopkey(): ?string
    {
        return $this->shopkey;
    }

    public function setShopkey(?string $shopkey): void
    {
        $this->shopkey = $shopkey;
    }

    public function getStart(): int
    {
        return $this->start;
    }

    public function setStart(int $start): void
    {
        $this->start = $start;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): void
    {
        $this->count = $count;
    }

    public function getProductId(): ?string
    {
        return $this->productId;
    }

    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }
}
