<?php

namespace App\Http\Controllers\Admin\Sacraments;

use App\Actions\Sacraments\SaveSacramentRecord;
use App\Enums\SacramentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sacraments\SaveSacramentRecordRequest;
use App\Models\Member;
use App\Models\SacramentRecord;
use App\Support\Sacraments\SacramentOptions;
use App\Support\Sacraments\SacramentPresenter;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class SacramentRecordController extends Controller
{
    /**
     * List the entries of the sacramental registers.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', SacramentRecord::class);

        $table = TableQuery::for(SacramentRecord::query()->with('member.media'), $request)
            ->searchable(['person_name', 'spouse_name', 'father_name', 'mother_name', 'minister', 'entry_no'])
            ->sortable(['received_on', 'person_name', 'created_at'], '-received_on')
            ->filter('type', function (Builder $query, string $type): void {
                $query->where('type', $type);
            })
            ->filter('year', function (Builder $query, string $year): void {
                $query->whereYear('received_on', (int) $year);
            })
            ->filter('place', function (Builder $query, string $place): void {
                $query->where('elsewhere', $place === 'elsewhere');
            });

        $earliest = SacramentRecord::query()->min('received_on');

        return Inertia::render('admin/sacraments/Index', [
            'records' => $table->paginate(20)->through(fn (SacramentRecord $record): array => SacramentPresenter::row($record)),
            'table' => $table->state(),
            'counts' => SacramentRecord::query()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'years' => $earliest !== null ? range((int) now()->format('Y'), (int) substr((string) $earliest, 0, 4)) : [],
            'options' => SacramentOptions::all(),
            'can' => ['create' => $request->user()->can('create', SacramentRecord::class)],
        ]);
    }

    /**
     * Show the form for a new register entry, optionally for a member.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', SacramentRecord::class);

        $type = SacramentType::tryFrom((string) $request->string('type')) ?? SacramentType::Baptism;

        return Inertia::render('admin/sacraments/Create', [
            'type' => $type->value,
            'person' => $this->person($request),
            'options' => SacramentOptions::all(),
        ]);
    }

    /**
     * Write a new register entry.
     */
    public function store(SaveSacramentRecordRequest $request, SaveSacramentRecord $save): RedirectResponse
    {
        $record = $save->handle($request->recordData(), $request->sponsors(), recordedBy: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':type of :name recorded.', ['type' => $record->type->label(), 'name' => $record->person_name])]);

        return to_route('admin.sacraments.show', $record);
    }

    /**
     * Show a register entry.
     */
    public function show(Request $request, SacramentRecord $sacrament): Response
    {
        Gate::authorize('view', $sacrament);

        $user = $request->user();
        $sacrament->load(['member.media', 'spouse.media', 'sponsors.member.media', 'recorder:id,name']);

        return Inertia::render('admin/sacraments/Show', [
            'record' => SacramentPresenter::detail($sacrament),
            'activity' => $sacrament->activitiesAsSubject()->with('causer:id,name')->latest()->limit(20)->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'causer' => $activity->causer?->getAttribute('name'),
                    'createdAt' => $activity->created_at?->toIso8601String(),
                ]),
            'can' => [
                'update' => $user->can('update', $sacrament),
                'delete' => $user->can('delete', $sacrament),
                'viewMembers' => $user->can('viewAny', Member::class),
            ],
        ]);
    }

    /**
     * Show the form for correcting a register entry.
     */
    public function edit(Request $request, SacramentRecord $sacrament): Response
    {
        Gate::authorize('update', $sacrament);

        $sacrament->load(['member.media', 'spouse.media', 'sponsors.member.media']);

        return Inertia::render('admin/sacraments/Edit', [
            'record' => [
                ...SacramentPresenter::detail($sacrament),
                'fields' => SacramentPresenter::form($sacrament),
            ],
            'person' => $this->person($request),
            'options' => SacramentOptions::all(),
        ]);
    }

    /**
     * Correct a register entry.
     */
    public function update(SaveSacramentRecordRequest $request, SacramentRecord $sacrament, SaveSacramentRecord $save): RedirectResponse
    {
        $save->handle($request->recordData(), $request->sponsors(), $sacrament);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Register entry saved.')]);

        return to_route('admin.sacraments.show', $sacrament);
    }

    /**
     * Remove a register entry. It stays in the database and the audit trail.
     */
    public function destroy(SacramentRecord $sacrament): RedirectResponse
    {
        Gate::authorize('delete', $sacrament);

        $sacrament->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The :type entry for :name was removed.', ['type' => strtolower($sacrament->type->label()), 'name' => $sacrament->person_name])]);

        return to_route('admin.sacraments.index');
    }

    /**
     * Get the details the form can copy from the member named in the query
     * string, which the form asks for whenever a member is picked.
     *
     * @return array<string, mixed>|null
     */
    private function person(Request $request): ?array
    {
        if (! $request->filled('member') || ! $request->user()->can('lookup', Member::class)) {
            return null;
        }

        $member = Member::query()->with(['media', 'family'])->find($request->integer('member'));

        return $member !== null ? SacramentPresenter::person($member) : null;
    }
}
