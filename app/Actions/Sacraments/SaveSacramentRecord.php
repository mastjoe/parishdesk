<?php

namespace App\Actions\Sacraments;

use App\Models\SacramentRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Writes a new register entry or corrects an existing one, together with
 * its godparents, sponsors or witnesses.
 */
class SaveSacramentRecord
{
    /**
     * Save the entry. The sponsors given replace the ones already named.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{member_id: int|null, name: string, role: string}>  $sponsors
     */
    public function handle(array $data, array $sponsors, ?SacramentRecord $record = null, ?User $recordedBy = null): SacramentRecord
    {
        return DB::transaction(function () use ($data, $sponsors, $record, $recordedBy): SacramentRecord {
            $record ??= new SacramentRecord;

            if (! $record->exists) {
                $record->recorded_by = $recordedBy?->id;
            }

            $record->fill($data)->save();

            $record->sponsors()->delete();
            $record->sponsors()->createMany($sponsors);

            return $record;
        });
    }
}
