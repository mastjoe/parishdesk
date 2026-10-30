<?php

namespace App\Http\Controllers\Admin\People;

use App\Enums\FamilyRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\AddFamilyMemberRequest;
use App\Models\Family;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class FamilyMemberController extends Controller
{
    /**
     * Add a member to the family, moving them from any other family.
     */
    public function store(AddFamilyMemberRequest $request, Family $family): RedirectResponse
    {
        $member = Member::query()->findOrFail($request->integer('member_id'));
        $moved = $member->family_id !== null && $member->family_id !== $family->id;

        $member->update([
            'family_id' => $family->id,
            'family_role' => $request->enum('family_role', FamilyRole::class),
            'community_id' => $member->community_id ?? $family->community_id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $moved
                ? __(':name moved into :family.', ['name' => $member->full_name, 'family' => $family->name])
                : __(':name added to :family.', ['name' => $member->full_name, 'family' => $family->name]),
        ]);

        return back();
    }

    /**
     * Change a member's role in the family.
     */
    public function update(Request $request, Family $family, Member $member): RedirectResponse
    {
        Gate::authorize('update', $family);
        abort_unless($member->family_id === $family->id, 404);

        $validated = $request->validate(['family_role' => ['required', Rule::enum(FamilyRole::class)]]);

        $member->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Family role updated.')]);

        return back();
    }

    /**
     * Take a member out of the family. They stay in the register.
     */
    public function destroy(Family $family, Member $member): RedirectResponse
    {
        Gate::authorize('update', $family);
        abort_unless($member->family_id === $family->id, 404);

        $member->update(['family_id' => null, 'family_role' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was removed from the family.', ['name' => $member->full_name])]);

        return back();
    }
}
