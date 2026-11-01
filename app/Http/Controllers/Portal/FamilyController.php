<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\People\MemberPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FamilyController extends Controller
{
    /**
     * Show the member's family as the parish has it on record.
     */
    public function __invoke(Request $request): Response
    {
        $member = $request->user()->member ?? abort(403);
        $family = $member->family()->with('community:id,name')->first();

        return Inertia::render('portal/Family', [
            'family' => $family !== null ? [
                'name' => $family->name,
                'address' => $family->address,
                'community' => $family->community?->name,
                'members' => $family->members()->with('media')->orderBy('date_of_birth')->get()
                    ->map(fn (Member $relative): array => [
                        ...MemberPresenter::summary($relative),
                        'isMe' => $relative->is($member),
                    ])
                    ->values(),
            ] : null,
        ]);
    }
}
