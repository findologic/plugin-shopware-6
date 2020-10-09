<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Errors;

class ExportErrors
{
    /** @var string[] */
    private $general = [];

    /** @var ProductError[] */
    private $products = [];

    public function hasErrors(): bool
    {
        if (!empty($this->general)) {
            return true;
        }

        foreach ($this->products as $product) {
            if (!empty($product->getErrors())) {
                return true;
            }
        }

        return false;
    }

    public function buildErrorResponse(): array
    {
        $productErrors = array_map(function (ProductError $product) {
            return $product->toArray();
        }, $this->products);

        return [
            'general' => $this->general,
            'products' => $productErrors,
        ];
    }

    public function addGeneralError(string $message): self
    {
        $this->general[] = $message;

        return $this;
    }

    public function addProductError(ProductError $productError): self
    {
        if (isset($this->products[$productError->getId()])) {
            $this->products[$productError->getId()]->addErrors($productError->getErrors());

            return $this;
        }

        $this->products[$productError->getId()] = $productError;

        return $this;
    }
}
