<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Errors;

class ExportErrors
{
    /** @var string[] */
    private array $general = [];

    /** @var ProductError[] */
    private array $products = [];

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
            'products' => array_values($productErrors),
        ];
    }

    public function addGeneralError(string $message): self
    {
        $this->general[] = $message;

        return $this;
    }

    public function addGeneralErrors(array $messages): self
    {
        $this->general = array_merge($this->general, $messages);

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

    public function getProductError(string $productId): ?ProductError
    {
        if (!isset($this->products[$productId])) {
            return null;
        }

        return $this->products[$productId];
    }

    /**
     * @return string[]
     */
    public function getGeneralErrors(): array
    {
        return $this->general;
    }
}
