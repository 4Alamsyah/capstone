<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AppSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * Get a setting value by key, with optional default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("app_setting.{$key}", function () use ($key, $default): mixed {
            $setting = static::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value by key (upsert).
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("app_setting.{$key}");
    }
}
