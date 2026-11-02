<?php

namespace App\Models;

use App\Enums\SponsorRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A godparent, sponsor or witness named in a sacramental register.
 *
 * @property int $id
 * @property int $sacrament_record_id
 * @property int|null $member_id
 * @property string $name
 * @property SponsorRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['member_id', 'name', 'role'])]
class SacramentSponsor extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => SponsorRole::class,
        ];
    }

    /**
     * Get the register entry the sponsor is named in.
     *
     * @return BelongsTo<SacramentRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(SacramentRecord::class, 'sacrament_record_id');
    }

    /**
     * Get the sponsor's member record, if they are in the register.
     *
     * @return BelongsTo<Member, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }
}
