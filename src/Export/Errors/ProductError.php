<?php

declare(strict_types=1);

namespace FINDOLOGIC\FinSearch\Export\Errors;

class ProductError
{
    private string $id;

    /** @var string[] */
    private array $errors;

    public function __construct(string $id, array $errors)
    {
        $this->id = $id;
        $this->errors = $errors;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'errors' => $this->errors,
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function addErrors(array $errors): self
    {
        $this->errors[] = array_merge($this->errors, $errors);

        return $this;
    }
}
