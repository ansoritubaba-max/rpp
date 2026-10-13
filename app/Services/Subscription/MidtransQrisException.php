<?php

namespace App\Services\Subscription;

use RuntimeException;

class MidtransQrisException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $userMessage = 'Pembayaran belum dapat diproses. Silakan coba lagi nanti.',
    ) {
        parent::__construct($message);
    }
}
