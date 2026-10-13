<?php

namespace App\Livewire\Concerns;

use App\Services\Documents\WizardDraftService;
use DomainException;
use Illuminate\Support\Facades\Log;
use Throwable;

trait PersistsWizardDraft
{
    public ?string $draftSavedAt = null;

    protected function restoreWizardDraft(string $featureCode, array $fields): bool
    {
        $draft = app(WizardDraftService::class)->find(auth()->user(), $featureCode);
        if (! $draft) {
            return false;
        }

        foreach ($fields as $field) {
            if (array_key_exists($field, $draft->payload_json ?? []) && property_exists($this, $field)) {
                $this->{$field} = $draft->payload_json[$field];
            }
        }

        $this->draftSavedAt = $draft->updated_at->locale('id')->translatedFormat('d M Y H:i');

        return true;
    }

    protected function persistWizardDraft(string $featureCode, array $fields): void
    {
        $payload = [];
        foreach ($fields as $field) {
            $payload[$field] = $this->{$field};
        }

        try {
            $draft = app(WizardDraftService::class)->save(auth()->user(), $featureCode, $payload);
            $this->draftSavedAt = $draft->updated_at->locale('id')->translatedFormat('d M Y H:i');
            $this->resetErrorBag('draft');
        } catch (DomainException $error) {
            $this->draftSavedAt = null;
            $this->addError('draft', $error->getMessage());
        } catch (Throwable $error) {
            Log::error('Wizard draft could not be saved', ['user_id' => auth()->id(), 'feature' => $featureCode, 'exception' => $error]);
            $this->draftSavedAt = null;
            $this->addError('draft', 'Draf belum tersimpan. Periksa koneksi lalu coba kembali.');
        }
    }

    protected function forgetWizardDraft(string $featureCode): void
    {
        app(WizardDraftService::class)->delete(auth()->user(), $featureCode);
        $this->draftSavedAt = null;
    }
}
