<?php

namespace App\Services\Imports;

class ImportResult
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /**
     * Errores legibles para el usuario
     */
    public array $errors = [];

    /**
     * Advertencias no fatales (opcional)
     */
    public array $warnings = [];

    public function addCreated(int $count = 1): void
    {
        $this->created += $count;
    }

    public function addUpdated(int $count = 1): void
    {
        $this->updated += $count;
    }

    public function addSkipped(int $count = 1): void
    {
        $this->skipped += $count;
    }

    public function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}