<?php

namespace FINDOLOGIC\FinSearch\Struct;

use Shopware\Core\Framework\Struct\Struct;

/**
 * Holds the state of usage of FINDOLOGIC. If false FINDOLOGIC search may not be used.
 */
class FindologicEnabled extends Struct
{
    private $enabled = true;

    public function setEnabled()
    {
        $this->enabled = true;
    }

    public function setDisabled(): void
    {
        $this->enabled = false;
    }

    public function getEnabled(): bool
    {
        return $this->enabled;
    }
}
