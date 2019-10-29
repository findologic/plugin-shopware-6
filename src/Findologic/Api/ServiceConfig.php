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
        $dateTime = new DateTime('now');
        $dateTime->modify(sprintf('+%d seconds', self::EXPIRE_TIME));

        $this->expireDateTime = $dateTime;
    }

    /**
     * @param mixed[] $options
     */
    public function setFromArray(array $options): void
    {
        foreach ($options as $key => $value) {
            if (is_callable([$this, "set$key"])) {
                $this->{"set$key"}($value);
            }
        }
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
