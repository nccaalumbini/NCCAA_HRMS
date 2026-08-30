<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SystemSetting extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = ['key', 'value'];

    /**
     * Get setting value by key, decrypting if necessary.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        try {
            $decrypted = Crypt::decryptString($setting->value);

            return json_decode($decrypted, true) ?? $decrypted;
        } catch (\Throwable) {
            return json_decode($setting->value, true) ?? $setting->value;
        }
    }

    /**
     * Set encrypted setting value.
     */
    public static function set(string $key, mixed $value): static
    {
        $serialized = is_array($value) || is_object($value) ? json_encode($value) : (string) $value;
        $encrypted = Crypt::encryptString($serialized);

        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $encrypted]
        );
    }
}
