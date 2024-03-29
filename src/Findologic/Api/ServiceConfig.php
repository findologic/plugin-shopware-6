<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Api;

use DateTime;
use Shopware\Core\Framework\Struct\Struct;

class ServiceConfig extends Struct
{
    private const EXPIRE_TIME = 60 * 60 * 24;

    protected array $directIntegration;

    protected bool $isStagingShop;

    protected DateTime $expireDateTime;

    /** @var array<string,string> */
    protected array $blocks;

    public function __construct()
    {
        // Add 24 hours to the current time to set expiration date
        $dateTime = new DateTime();
        $dateTime = $dateTime->modify(sprintf('+%d seconds', self::EXPIRE_TIME));

        $this->expireDateTime = $dateTime;
    }

    public function getExpireDateTime(): DateTime
    {
        return $this->expireDateTime;
    }

    public function getDirectIntegration(): array
    {
        return $this->directIntegration;
    }

    public function setDirectIntegration(array $directIntegration): void
    {
        $this->directIntegration = $directIntegration;
    }

    public function getIsStagingShop(): bool
    {
        return $this->isStagingShop;
    }

    public function setIsStagingShop(bool $isStagingShop): void
    {
        $this->isStagingShop = $isStagingShop;
    }

    /**
     * @return array<string,string>
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @param array<string,string> $blocks
     */
    public function setBlocks(array $blocks): void
    {
        $this->blocks = $blocks;
    }
}
