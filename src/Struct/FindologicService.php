<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Holds the state of usage of FINDOLOGIC. If false FINDOLOGIC search may not be used.
 */
class FindologicService extends Struct
{
    private $enabled = true;

    public function setEnabled(): bool
    {
        $this->enabled = true;

        return $this->enabled;
    }

    public function setDisabled(): bool
    {
        $this->enabled = false;

        return $this->enabled;
    }

    /**
     * Note: Views will automatically call this method to receive the value. Unfortunately views can
     * not detect "isEnabled".
     */
    public function getEnabled(): bool
    {
        return $this->enabled;
    }
}
