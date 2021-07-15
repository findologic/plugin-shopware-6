<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Findologic\Config;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class FinSearchConfigEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $configurationKey;

    /**
     * @var mixed
     */
    protected $configurationValue;

    /**
     * @var string|null
     */
    protected $salesChannelId;

    /**
     * @var string|null
     */
    protected $languageId;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var LanguageEntity|null
     */
    protected $language;

    public function getConfigurationKey(): string
    {
        return $this->configurationKey;
    }

    public function setConfigurationKey(string $configurationKey): void
    {
        $this->configurationKey = $configurationKey;
    }

    public function getConfigurationValue()
    {
        return $this->configurationValue;
    }

    public function setConfigurationValue($configurationValue): void
    {
        $this->configurationValue = $configurationValue;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getLanguageId(): ?string
    {
        return $this->languageId;
    }

    public function setLanguageId(?string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(?LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
