<?php

namespace App\Http\Controllers\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\SaveClergyAssignmentRequest;
use App\Models\ClergyAssignment;
use App\Support\People\PeopleOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ClergyAssignmentController extends Controller
{
    /**
     * Show the clergy who serve and have served the parish.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ClergyAssignment::class);

        return Inertia::render('admin/people/clergy/Index', [
            'assignments' => ClergyAssignment::query()->with('member')
                ->orderByRaw('ends_on is null desc')
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (ClergyAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'name' => $assignment->name,
                    'role' => $assignment->role->value,
                    'roleLabel' => $assignment->role->label(),
                    'starts_on' => $assignment->starts_on->toDateString(),
                    'ends_on' => $assignment->ends_on?->toDateString(),
                    'notes' => $assignment->notes,
                    'isCurrent' => $assignment->isCurrent(),
                    'member' => $assignment->member !== null ? [
                        'id' => $assignment->member->id,
                        'name' => $assignment->member->full_name,
                        'memberNo' => $assignment->member->member_no,
                    ] : null,
                ]),
            'options' => PeopleOptions::enums(),
            'can' => ['manage' => $request->user()->can('create', ClergyAssignment::class)],
        ]);
    }

    /**
     * Record a clergy assignment.
     */
    public function store(SaveClergyAssignmentRequest $request): RedirectResponse
    {
        ClergyAssignment::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment recorded.')]);

        return back();
    }

    /**
     * Update a clergy assignment.
     */
    public function update(SaveClergyAssignmentRequest $request, ClergyAssignment $clergy): RedirectResponse
    {
        $clergy->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment saved.')]);

        return back();
    }

    /**
     * Delete a clergy assignment.
     */
    public function destroy(ClergyAssignment $clergy): RedirectResponse
    {
        Gate::authorize('delete', $clergy);

        $clergy->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assignment deleted.')]);

        return back();
    }
}
