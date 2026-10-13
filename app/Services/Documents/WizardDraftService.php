<?php

namespace App\Services\Documents;

use App\Models\User;
use App\Models\WizardDraft;
use DomainException;

class WizardDraftService
{
    public function find(User $user, string $featureCode): ?WizardDraft
    {
        return WizardDraft::query()
            ->where('user_id', $user->id)
            ->where('feature_code', $featureCode)
            ->first();
    }

    public function save(User $user, string $featureCode, array $payload): WizardDraft
    {
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        if (strlen($encoded) > 65536) {
            throw new DomainException('Draf terlalu besar untuk disimpan. Ringkas catatan atau daftar materi terlebih dahulu.');
        }

        return WizardDraft::query()->updateOrCreate(
            ['user_id' => $user->id, 'feature_code' => $featureCode],
            ['payload_json' => $payload],
        );
    }

    public function delete(User $user, string $featureCode): void
    {
        WizardDraft::query()
            ->where('user_id', $user->id)
            ->where('feature_code', $featureCode)
            ->delete();
    }
}
