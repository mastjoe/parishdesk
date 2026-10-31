<?php

namespace App\Models;

use App\Enums\FamilyRole;
use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A household in the parish. Each member records their own role in it, so
 * the head of family is the member whose family role is "head".
 *
 * @property int $id
 * @property string $name
 * @property int|null $community_id
 * @property string|null $address
 * @property string|null $home_phone
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'community_id', 'address', 'home_phone', 'notes'])]
class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * Get the community the family belongs to.
     *
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Get the members of the family.
     *
     * @return HasMany<Member, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get the head of the family.
     *
     * @return HasOne<Member, $this>
     */
    public function head(): HasOne
    {
        return $this->hasOne(Member::class)->where('family_role', FamilyRole::Head)->oldestOfMany();
    }

    /**
     * Place a member in the family with the given role, moving them from any
     * other family. A member without a community joins the family's.
     */
    public function addMember(Member $member, FamilyRole $role): void
    {
        $member->update([
            'family_id' => $this->id,
            'family_role' => $role,
            'community_id' => $member->community_id ?? $this->community_id,
        ]);
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
