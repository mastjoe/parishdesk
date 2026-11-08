<?php

namespace App\Http\Controllers\Admin\Marriages;

use App\Actions\Marriages\SaveMarriageApplication;
use App\Enums\MarriageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Marriages\SaveMarriageApplicationRequest;
use App\Models\MarriageApplication;
use App\Models\Member;
use App\Models\SacramentRecord;
use App\Support\Marriages\MarriagePresenter;
use App\Support\People\MemberPresenter;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class MarriageApplicationController extends Controller
{
    /**
     * List the marriage applications.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', MarriageApplication::class);

        $table = TableQuery::for(MarriageApplication::query()->with(['groom.media', 'bride.media', 'publications', 'objections']), $request)
            ->searchable(['application_no', 'groom_name', 'bride_name', 'officiant'])
            ->sortable(['created_at', 'proposed_date', 'groom_name'], '-created_at')
            ->filter('status', function (Builder $query, string $status): void {
                $status === 'open'
                    ? $query->whereIn('status', MarriageStatus::open())
                    : $query->where('status', $status);
            });

        return Inertia::render('admin/marriages/Index', [
            'applications' => $table->paginate(20)->through(fn (MarriageApplication $application): array => MarriagePresenter::row($application)),
            'table' => $table->state(),
            'counts' => MarriageApplication::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => MarriageStatus::options(),
            'can' => ['create' => $request->user()->can('create', MarriageApplication::class)],
        ]);
    }

    /**
     * Show the form for a new application, optionally for a member.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', MarriageApplication::class);

        $member = $request->filled('member') && $request->user()->can('lookup', Member::class)
            ? Member::query()->with('media')->find($request->integer('member'))
            : null;

        return Inertia::render('admin/marriages/Create', [
            'member' => $member !== null ? MemberPresenter::summary($member) : null,
        ]);
    }

    /**
     * Open a marriage application.
     */
    public function store(SaveMarriageApplicationRequest $request, SaveMarriageApplication $save): RedirectResponse
    {
        $application = $save->handle($request->validated(), createdBy: $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marriage application :number opened for :couple.', ['number' => $application->application_no, 'couple' => $application->coupleName()])]);

        return to_route('admin.marriages.show', $application);
    }

    /**
     * Show an application with its checks, banns and objections.
     */
    public function show(Request $request, MarriageApplication $marriage): Response
    {
        Gate::authorize('view', $marriage);

        $user = $request->user();
        $marriage->load(['groom.media', 'bride.media', 'publications.recorder:id,name', 'objections.resolver:id,name', 'creator:id,name']);

        return Inertia::render('admin/marriages/Show', [
            'application' => MarriagePresenter::detail($marriage),
            'activity' => $marriage->activitiesAsSubject()->with('causer:id,name')->latest()->limit(20)->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'causer' => $activity->causer?->getAttribute('name'),
                    'createdAt' => $activity->created_at?->toIso8601String(),
                ]),
            'can' => [
                'update' => $user->can('update', $marriage),
                'recordWedding' => $user->can('recordWedding', $marriage),
                'delete' => $user->can('delete', $marriage),
                'viewMembers' => $user->can('viewAny', Member::class),
                'viewSacraments' => $user->can('viewAny', SacramentRecord::class),
            ],
        ]);
    }

    /**
     * Show the form for changing the couple's details.
     */
    public function edit(MarriageApplication $marriage): Response
    {
        Gate::authorize('update', $marriage);

        $marriage->load(['groom.media', 'bride.media', 'publications', 'objections']);

        return Inertia::render('admin/marriages/Edit', [
            'application' => [
                ...MarriagePresenter::row($marriage),
                'fields' => MarriagePresenter::form($marriage),
            ],
        ]);
    }

    /**
     * Change the couple's details.
     */
    public function update(SaveMarriageApplicationRequest $request, MarriageApplication $marriage, SaveMarriageApplication $save): RedirectResponse
    {
        $save->handle($request->validated(), $marriage);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marriage application saved.')]);

        return to_route('admin.marriages.show', $marriage);
    }

    /**
     * Remove an application opened by mistake. It stays in the database
     * and the audit trail.
     */
    public function destroy(MarriageApplication $marriage): RedirectResponse
    {
        Gate::authorize('delete', $marriage);

        $marriage->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Marriage application :number was removed.', ['number' => $marriage->application_no])]);

        return to_route('admin.marriages.index');
    }
}
