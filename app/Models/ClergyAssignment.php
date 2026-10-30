<?php

namespace App\Models;

use App\Enums\ClergyRole;
use Database\Factories\ClergyAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A period during which a priest or deacon served the parish.
 *
 * The name is always stored, so priests who were never registered as
 * members still appear in the history.
 *
 * @property int $id
 * @property int|null $member_id
 * @property string $name
 * @property ClergyRole $role
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['member_id', 'name', 'role', 'starts_on', 'ends_on', 'notes'])]
class ClergyAssignment extends Model
{
    /** @use HasFactory<ClergyAssignmentFactory> */
    use HasFactory, LogsActivity;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ClergyRole::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Get the member record of the priest or deacon, if there is one.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    /**
     * Scope the query to assignments that have not ended.
     *
     * @param  Builder<ClergyAssignment>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('starts_on', '<=', today())
            ->where(fn (Builder $query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', today()));
    }

    /**
     * Determine whether the assignment is still running.
     */
    public function isCurrent(): bool
    {
        return $this->starts_on->lte(today()) && ($this->ends_on === null || $this->ends_on->gte(today()));
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
            ->useLogName('people');
    }
}
