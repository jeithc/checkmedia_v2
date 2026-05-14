<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class MaintenanceCategory extends Model
{
    protected $fillable = ['key', 'label', 'order_index'];

    /**
     * Returns ['key' => 'label'] ordered, cached 5 min.
     */
    public static function options(): array
    {
        return Cache::remember('maintenance_categories.options', 300, fn () =>
            static::query()
                ->orderBy('order_index')
                ->pluck('label', 'key')
                ->toArray()
        );
    }

    public static function label(?string $key): string
    {
        if (!$key) {
            return '—';
        }

        return static::options()[$key] ?? ucfirst($key);
    }

    public static function forgetCache(): void
    {
        Cache::forget('maintenance_categories.options');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::forgetCache());
        static::deleted(fn () => static::forgetCache());
    }
}
