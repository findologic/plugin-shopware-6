<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Holds the state of usage of FINDOLOGIC. If false FINDOLOGIC search may not be used.
 */
class FindologicService extends Struct
{
    private $enabled = false;

    private $smartSuggestEnabled = false;

    public function enable(): bool
    {
        $this->enabled = true;

        return $this->enabled;
    }

    public function disable(): bool
    {
        $this->enabled = false;

        return $this->enabled;
    }

    public function enableSmartSuggest(): bool
    {
        $this->smartSuggestEnabled = true;

        return $this->smartSuggestEnabled;
    }

    public function disableSmartSuggest(): bool
    {
        $this->smartSuggestEnabled = false;

        return $this->smartSuggestEnabled;
    }

    /**
     * Note: Views will automatically call this method to receive the value. Unfortunately views can
     * not detect "isEnabled".
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }

    public function getSmartSuggestEnabled(): bool
    {
        return $this->smartSuggestEnabled;
    }
}
