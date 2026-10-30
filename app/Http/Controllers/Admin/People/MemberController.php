<?php

namespace App\Http\Controllers\Admin\People;

use App\Actions\Members\SaveMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\SaveMemberRequest;
use App\Models\Family;
use App\Models\Member;
use App\Models\ProfileChangeRequest;
use App\Models\UserInvitation;
use App\Support\People\MemberPresenter;
use App\Support\People\MemberTable;
use App\Support\People\PeopleOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class MemberController extends Controller
{
    /**
     * List the member register.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Member::class);

        $user = $request->user();
        $table = MemberTable::for($request);

        return Inertia::render('admin/people/members/Index', [
            'members' => $table->paginate(20)->through(fn (Member $member): array => MemberPresenter::row($member)),
            'table' => $table->state(),
            'communities' => PeopleOptions::communities(),
            'options' => PeopleOptions::enums(),
            'counts' => [
                'total' => Member::query()->count(),
                'active' => Member::query()->where('status', 'active')->count(),
                'newThisMonth' => Member::query()->where('created_at', '>=', now()->startOfMonth())->count(),
                'withAccount' => Member::query()->whereNotNull('user_id')->count(),
            ],
            'can' => [
                'create' => $user->can('create', Member::class),
                'import' => $user->can('import', Member::class),
                'export' => $user->can('export', Member::class),
            ],
        ]);
    }

    /**
     * Show the form for registering a member, optionally into a family.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('create', Member::class);

        $family = $request->filled('family') ? Family::query()->find($request->integer('family')) : null;

        return Inertia::render('admin/people/members/Create', [
            'communities' => PeopleOptions::communities(),
            'options' => PeopleOptions::enums(),
            'family' => $family !== null ? ['id' => $family->id, 'name' => $family->name, 'communityId' => $family->community_id] : null,
        ]);
    }

    /**
     * Register a new member.
     */
    public function store(SaveMemberRequest $request, SaveMember $saveMember): RedirectResponse
    {
        $member = $saveMember->handle($request->memberData(), photo: $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name registered as :number.', ['name' => $member->full_name, 'number' => $member->member_no])]);

        return to_route('admin.members.show', $member);
    }

    /**
     * Show a member's profile.
     */
    public function show(Request $request, Member $member): Response
    {
        Gate::authorize('view', $member);

        $user = $request->user();
        $member->load(['community:id,name', 'family.community:id,name', 'user:id,email,last_login_at', 'latestInvitation']);

        $relatives = $member->family?->members()
            ->whereKeyNot($member->id)
            ->orderByRaw("case family_role when 'head' then 0 when 'spouse' then 1 when 'child' then 2 else 3 end")
            ->orderBy('date_of_birth')
            ->get() ?? collect();

        $invitation = $member->latestInvitation;

        return Inertia::render('admin/people/members/Show', [
            'member' => [
                ...MemberPresenter::row($member),
                ...MemberPresenter::form($member),
                'genderLabel' => $member->gender->label(),
                'maritalStatus' => $member->marital_status?->label(),
                'familyRoleLabel' => $member->family_role?->label(),
                'statusLabel' => $member->status->label(),
                'dateOfBirth' => $member->date_of_birth?->toDateString(),
                'createdAt' => $member->created_at?->toIso8601String(),
            ],
            'family' => $member->family !== null ? [
                'id' => $member->family->id,
                'name' => $member->family->name,
                'address' => $member->family->address,
                'community' => $member->family->community?->name,
                'members' => $relatives->map(fn (Member $relative): array => MemberPresenter::summary($relative))->values(),
            ] : null,
            'portal' => [
                'account' => $member->user !== null ? [
                    'email' => $member->user->email,
                    'lastLoginAt' => $member->user->last_login_at?->toIso8601String(),
                ] : null,
                'invitation' => $invitation !== null && $member->user === null ? [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'status' => $invitation->status(),
                    'expiresAt' => $invitation->expires_at->toIso8601String(),
                ] : null,
            ],
            'changeRequests' => $member->changeRequests()->with('reviewer:id,name')->latest()->limit(10)->get()
                ->map(fn (ProfileChangeRequest $change): array => [
                    'id' => $change->id,
                    'status' => $change->status->value,
                    'changes' => $change->changes,
                    'original' => $change->original,
                    'note' => $change->note,
                    'reviewNote' => $change->review_note,
                    'reviewer' => $change->reviewer?->name,
                    'createdAt' => $change->created_at?->toIso8601String(),
                    'reviewedAt' => $change->reviewed_at?->toIso8601String(),
                ]),
            'activity' => $member->activitiesAsSubject()->with('causer:id,name')->latest()->limit(20)->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'causer' => $activity->causer?->getAttribute('name'),
                    'createdAt' => $activity->created_at?->toIso8601String(),
                ]),
            'options' => PeopleOptions::enums(),
            'can' => [
                'update' => $user->can('update', $member),
                'delete' => $user->can('delete', $member),
                'invite' => $user->can('invite', $member),
                'reviewChanges' => $user->can('viewAny', ProfileChangeRequest::class),
                'viewFamily' => $user->can('viewAny', Family::class),
            ],
            'invitationValidDays' => UserInvitation::VALID_FOR_DAYS,
        ]);
    }

    /**
     * Show the form for editing a member.
     */
    public function edit(Member $member): Response
    {
        Gate::authorize('update', $member);

        $member->load('family:id,name');

        return Inertia::render('admin/people/members/Edit', [
            'member' => [
                'id' => $member->id,
                'memberNo' => $member->member_no,
                'name' => $member->full_name,
                'photoUrl' => $member->photoUrl(),
                ...MemberPresenter::form($member),
            ],
            'family' => $member->family !== null ? ['id' => $member->family->id, 'name' => $member->family->name] : null,
            'communities' => PeopleOptions::communities(),
            'options' => PeopleOptions::enums(),
        ]);
    }

    /**
     * Update a member.
     */
    public function update(SaveMemberRequest $request, Member $member, SaveMember $saveMember): RedirectResponse
    {
        $saveMember->handle($request->memberData(), $member, $request->file('photo'), $request->boolean('remove_photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Details saved for :name.', ['name' => $member->full_name])]);

        return to_route('admin.members.show', $member);
    }

    /**
     * Remove a member from the register. The record is kept for history.
     */
    public function destroy(Member $member): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $member->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was removed from the register.', ['name' => $member->full_name])]);

        return to_route('admin.members.index');
    }
}
