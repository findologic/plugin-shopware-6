<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Api;

use DateTime;
use Shopware\Core\Framework\Struct\Struct;

class ServiceConfig extends Struct
{
    private const EXPIRE_TIME = 60 * 60 * 24;

    /** @var array */
    protected $directIntegration;

    /** @var bool */
    protected $isStagingShop;

    /** @var DateTime */
    protected $expireDateTime;

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
}
