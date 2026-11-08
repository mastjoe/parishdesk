<?php

namespace App\Models;

use Database\Factories\BannsObjectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An impediment someone made known after the banns were announced. It
 * holds up the marriage until the parish records how it was resolved.
 *
 * @property int $id
 * @property int $marriage_application_id
 * @property string $raised_by
 * @property Carbon $raised_on
 * @property string $details
 * @property string|null $resolution
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property int|null $recorded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['raised_by', 'raised_on', 'details', 'recorded_by'])]
class BannsObjection extends Model
{
    /** @use HasFactory<BannsObjectionFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'raised_on' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * Get the application the objection was raised against.
     *
     * @return BelongsTo<MarriageApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(MarriageApplication::class, 'marriage_application_id');
    }

    /**
     * Get the user who resolved the objection.
     *
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Whether the objection is still waiting to be resolved.
     */
    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * Configure which attributes are written to the audit log.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['marriage_application_id', 'raised_by', 'raised_on', 'details', 'resolution', 'resolved_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('marriages');
    }
}
