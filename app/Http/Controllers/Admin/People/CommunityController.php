<?php

namespace App\Http\Controllers\Admin\People;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\SaveCommunityRequest;
use App\Models\Community;
use App\Models\Member;
use App\Support\People\MemberPresenter;
use App\Support\People\PeopleOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    /**
     * List the communities with their leaders and size.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Community::class);

        $user = $request->user();

        return Inertia::render('admin/people/communities/Index', [
            'communities' => Community::query()->with('leader.media')->withCount(['members', 'families'])->orderBy('name')->get()
                ->map(fn (Community $community): array => [
                    'id' => $community->id,
                    'name' => $community->name,
                    'type' => $community->type->value,
                    'typeLabel' => $community->type->label(),
                    'description' => $community->description,
                    'leader' => $community->leader !== null ? MemberPresenter::summary($community->leader) : null,
                    'membersCount' => $community->members_count,
                    'familiesCount' => $community->families_count,
                ]),
            'can' => [
                'manage' => $user->can('create', Community::class),
                'viewMembers' => $user->can('viewAny', Member::class),
            ],
        ]);
    }

    /**
     * Show the form for a new community.
     */
    public function create(): Response
    {
        Gate::authorize('create', Community::class);

        return Inertia::render('admin/people/communities/Create', [
            'options' => PeopleOptions::enums(),
        ]);
    }

    /**
     * Create a community.
     */
    public function store(SaveCommunityRequest $request): RedirectResponse
    {
        $community = Community::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name created.', ['name' => $community->name])]);

        return to_route('admin.communities.index');
    }

    /**
     * Show the form for editing a community.
     */
    public function edit(Community $community): Response
    {
        Gate::authorize('update', $community);

        $community->load('leader.media')->loadCount(['members', 'families']);

        return Inertia::render('admin/people/communities/Edit', [
            'community' => [
                'id' => $community->id,
                'name' => $community->name,
                'type' => $community->type->value,
                'description' => $community->description,
                'leader' => $community->leader !== null ? MemberPresenter::summary($community->leader) : null,
                'membersCount' => $community->members_count,
                'familiesCount' => $community->families_count,
            ],
            'options' => PeopleOptions::enums(),
        ]);
    }

    /**
     * Update a community.
     */
    public function update(SaveCommunityRequest $request, Community $community): RedirectResponse
    {
        $community->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name saved.', ['name' => $community->name])]);

        return to_route('admin.communities.index');
    }

    /**
     * Delete a community. Its members and families stay, without a community.
     */
    public function destroy(Community $community): RedirectResponse
    {
        Gate::authorize('delete', $community);

        $community->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deleted.', ['name' => $community->name])]);

        return back();
    }
}
