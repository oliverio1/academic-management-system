<?php

namespace App\Services\AdminAlerts\Contracts;

use App\DTOs\AdminAlert;

interface AlertGenerator {
    public function generate(): ?AdminAlert;
}