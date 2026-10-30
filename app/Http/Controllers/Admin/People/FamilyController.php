<?php

namespace App\Http\Controllers\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\SaveFamilyRequest;
use App\Models\Family;
use App\Models\Member;
use App\Support\People\MemberPresenter;
use App\Support\People\PeopleOptions;
use App\Support\Table\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FamilyController extends Controller
{
    /**
     * List families with their head and size.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Family::class);

        $table = TableQuery::for(Family::query()->with(['community:id,name', 'head'])->withCount('members'), $request)
            ->searchable(['name', 'address', 'home_phone'])
            ->sortable(['name', 'members_count', 'created_at'], 'name')
            ->filter('community', function (Builder $query, string $community): void {
                $community === 'none' ? $query->whereNull('community_id') : $query->where('community_id', (int) $community);
            });

        return Inertia::render('admin/people/families/Index', [
            'families' => $table->paginate(20)->through(fn (Family $family): array => [
                'id' => $family->id,
                'name' => $family->name,
                'address' => $family->address,
                'community' => $family->community?->name,
                'head' => $family->head?->full_name,
                'membersCount' => $family->members_count,
            ]),
            'table' => $table->state(),
            'communities' => PeopleOptions::communities(),
            'can' => ['create' => $request->user()->can('create', Family::class)],
        ]);
    }

    /**
     * Create a family.
     */
    public function store(SaveFamilyRequest $request): RedirectResponse
    {
        $family = Family::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created. Now add its members.', ['name' => $family->name])]);

        return to_route('admin.families.show', $family);
    }

    /**
     * Show a family and its members, arranged as a family tree.
     */
    public function show(Request $request, Family $family): Response
    {
        Gate::authorize('view', $family);

        $user = $request->user();
        $family->load('community:id,name');

        return Inertia::render('admin/people/families/Show', [
            'family' => [
                'id' => $family->id,
                'name' => $family->name,
                'community_id' => $family->community_id,
                'community' => $family->community?->name,
                'address' => $family->address,
                'home_phone' => $family->home_phone,
                'notes' => $family->notes,
                'createdAt' => $family->created_at?->toIso8601String(),
            ],
            'members' => $family->members()->with('media')->orderBy('date_of_birth')->get()
                ->map(fn (Member $member): array => [
                    ...MemberPresenter::summary($member),
                    'phone' => $member->phone,
                ])
                ->values(),
            'communities' => PeopleOptions::communities(),
            'options' => PeopleOptions::enums(),
            'can' => [
                'update' => $user->can('update', $family),
                'delete' => $user->can('delete', $family),
                'createMember' => $user->can('create', Member::class),
                'viewMembers' => $user->can('viewAny', Member::class),
            ],
        ]);
    }

    /**
     * Update a family's details.
     */
    public function update(SaveFamilyRequest $request, Family $family): RedirectResponse
    {
        $family->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Family details saved.')]);

        return back();
    }

    /**
     * Delete a family. Its members stay in the register without a family.
     */
    public function destroy(Family $family): RedirectResponse
    {
        Gate::authorize('delete', $family);

        DB::transaction(function () use ($family): void {
            $family->members()->each(fn (Member $member) => $member->update(['family_id' => null, 'family_role' => null]));
            $family->delete();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deleted.', ['name' => $family->name])]);

        return to_route('admin.families.index');
    }
}
