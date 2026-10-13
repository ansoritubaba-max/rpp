<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'reference_price' => 'decimal:2',
            'is_active' => 'boolean',
            'show_reference_price' => 'boolean',
            'is_recommended' => 'boolean',
            'sort_order' => 'integer',
            'discount_percent' => 'integer',
            'discount_ends_at' => 'datetime',
        ];
    }

    public function currentPrice(): int
    {
        $price = (int) round((float) $this->price);
        if ($this->discount_percent <= 0 || ! $this->discount_ends_at?->isFuture()) {
            return $price;
        }

        return (int) round($price * (100 - $this->discount_percent) / 100);
    }

    public function hasActiveDiscount(): bool
    {
        return $this->currentPrice() < (int) round((float) $this->price);
    }

    public function hasVisibleReferencePrice(): bool
    {
        return $this->show_reference_price
            && $this->reference_price !== null
            && (float) $this->reference_price > $this->currentPrice();
    }

    public function savingPercent(): int
    {
        $base = $this->hasVisibleReferencePrice()
            ? (float) $this->reference_price
            : (float) $this->price;

        return $base > 0 ? (int) round(100 * ($base - $this->currentPrice()) / $base) : 0;
    }

    public function totalMonthlyGenerations(): int
    {
        return $this->features->sum(fn ($feature) => (int) ($feature->pivot->generation_limit ?? 0));
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'plan_features')
            ->withPivot(['is_enabled', 'generation_limit', 'regeneration_limit']);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
