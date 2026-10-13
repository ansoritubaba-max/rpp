<?php

namespace App\Services\Auth;

use DomainException;

class MailDeliveryGuard
{
    public function isReady(): bool
    {
        $mailer = config('mail.default');

        return in_array($mailer, ['smtp', 'sendmail', 'ses', 'ses-v2', 'postmark', 'resend', 'mailgun'], true);
    }

    public function ensureReady(): void
    {
        if (! $this->isReady()) {
            throw new DomainException('Pengiriman email belum dikonfigurasi. Hubungi pengelola aplikasi.');
        }
    }
}
