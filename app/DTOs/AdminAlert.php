<?php

namespace App\DTOs;

class AdminAlert
{
    public function __construct (
        public string $icon, 
        public string $message, 
        public string $url, 
        public string $level = 'warning',
        public ?string $dateRange = null
    ) {}
}
