<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /**
     * Browse the audit trail.
     */
    public function __invoke(Request $request): Response
    {
        $table = TableQuery::for(Activity::query()->with('causer'), $request)
            ->searchable(['description'])
            ->sortable(['created_at'], '-created_at')
            ->filter('log', function (Builder $query, string $log): void {
                $query->where('log_name', $log);
            })
            ->filter('causer', function (Builder $query, string $causer): void {
                $query->where('causer_id', $causer);
            });

        $entries = $table->paginate(25)->through(fn (Activity $activity): array => [
            'id' => $activity->id,
            'log' => $activity->log_name,
            'event' => $activity->event,
            'description' => $activity->description,
            'subject' => $activity->subject_type ? Str::headline(class_basename($activity->subject_type)).' #'.$activity->subject_id : null,
            'causer' => $activity->causer?->getAttribute('name'),
            'changes' => $activity->attribute_changes,
            'properties' => $activity->properties,
            'createdAt' => $activity->created_at?->toIso8601String(),
        ]);

        return Inertia::render('admin/activity/Index', [
            'entries' => $entries,
            'table' => $table->state(),
            'logs' => Activity::query()->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values(),
        ]);
    }
}
