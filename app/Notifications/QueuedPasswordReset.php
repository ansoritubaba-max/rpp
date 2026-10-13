<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Password;

class QueuedPasswordReset extends ResetPassword implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable;

    public int $tries = 3;

    public function __construct(string $token)
    {
        parent::__construct($token);
        $this->onQueue('mail')->afterCommit();
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return Password::tokenExists($notifiable, $this->token);
    }
}
