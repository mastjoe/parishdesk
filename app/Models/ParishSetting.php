<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * The single row holding the parish profile, branding and defaults.
 *
 * @property int $id
 * @property string $name
 * @property string|null $patron_saint
 * @property string|null $diocese
 * @property string|null $deanery
 * @property string|null $address
 * @property string|null $city
 * @property string|null $state
 * @property string|null $country
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $website
 * @property string|null $logo_path
 * @property string $theme
 * @property string|null $custom_color
 * @property string $currency
 * @property string $timezone
 * @property string $locale
 * @property int $fiscal_year_start_month
 * @property string|null $bank_name
 * @property string|null $bank_account_name
 * @property string|null $bank_account_number
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'patron_saint', 'diocese', 'deanery', 'address', 'city', 'state', 'country', 'phone', 'email', 'website',
    'logo_path', 'theme', 'custom_color', 'currency', 'timezone', 'locale', 'fiscal_year_start_month',
    'bank_name', 'bank_account_name', 'bank_account_number',
])]
class ParishSetting extends Model
{
    use LogsActivity;

    /**
     * The cache key holding the current settings row.
     */
    private const string CACHE_KEY = 'parish-settings';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fiscal_year_start_month' => 'integer',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Get the parish settings, creating the row with defaults when missing.
     *
     * Only the raw attributes are cached, because the cache store refuses to
     * unserialize objects (see "serializable_classes" in config/cache.php).
     */
    public static function current(): self
    {
        $attributes = Cache::rememberForever(
            self::CACHE_KEY,
            fn (): array => self::query()->firstOrCreate([], ['name' => config('app.name')])->getAttributes(),
        );

        return (new self)->newFromBuilder($attributes);
    }

    /**
     * Get the public URL of the parish logo.
     */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * Configure which attributes are written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('settings');
    }
}
