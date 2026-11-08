<?php

namespace App\Models;

use Database\Factories\BannsPublicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One of the three times the banns of a marriage were announced.
 *
 * @property int $id
 * @property int $marriage_application_id
 * @property int $number 1, 2 or 3
 * @property Carbon $published_on
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['number', 'published_on', 'recorded_by'])]
class BannsPublication extends Model
{
    /** @use HasFactory<BannsPublicationFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'published_on' => 'date',
        ];
    }

    /**
     * Get the application whose banns were published.
     *
     * @return BelongsTo<MarriageApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(MarriageApplication::class, 'marriage_application_id');
    }

    /**
     * Get the user who recorded the publication.
     *
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Configure which attributes are written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['marriage_application_id', 'number', 'published_on'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('marriages');
    }
}
