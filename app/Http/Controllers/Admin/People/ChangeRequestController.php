<?php

namespace App\Http\Controllers\Admin\People;

use App\Actions\Members\ReviewProfileChange;
use App\Enums\ChangeRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\ReviewChangeRequestRequest;
use App\Models\ProfileChangeRequest;
use App\Support\People\MemberPresenter;
use App\Support\People\PeopleOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChangeRequestController extends Controller
{
    /**
     * List profile change requests, waiting ones first.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ProfileChangeRequest::class);

        $status = ChangeRequestStatus::tryFrom((string) $request->query('status')) ?? ChangeRequestStatus::Pending;
        $user = $request->user();

        $requests = ProfileChangeRequest::query()
            ->with(['member' => fn ($query) => $query->withTrashed()->with('media'), 'submitter:id,name', 'reviewer:id,name'])
            ->where('status', $status)
            ->orderBy($status === ChangeRequestStatus::Pending ? 'created_at' : 'reviewed_at', $status === ChangeRequestStatus::Pending ? 'asc' : 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (ProfileChangeRequest $change): array => [
                'id' => $change->id,
                'member' => MemberPresenter::summary($change->member),
                'changes' => $change->changes,
                'original' => $change->original,
                'current' => array_intersect_key(ProfileChangeRequest::currentValues($change->member), $change->changes),
                'note' => $change->note,
                'status' => $change->status->value,
                'reviewNote' => $change->review_note,
                'reviewer' => $change->reviewer?->name,
                'createdAt' => $change->created_at?->toIso8601String(),
                'reviewedAt' => $change->reviewed_at?->toIso8601String(),
                'canReview' => $user->can('review', $change),
            ]);

        return Inertia::render('admin/people/change-requests/Index', [
            'requests' => $requests,
            'status' => $status->value,
            'pendingCount' => ProfileChangeRequest::query()->pending()->count(),
            'occupationLabels' => PeopleOptions::occupationLabels(),
        ]);
    }

    /**
     * Approve or reject a request.
     */
    public function update(ReviewChangeRequestRequest $request, ProfileChangeRequest $changeRequest, ReviewProfileChange $review): RedirectResponse
    {
        $note = $request->filled('review_note') ? $request->string('review_note')->value() : null;

        if ($request->input('decision') === 'approve') {
            $review->approve($request->user(), $changeRequest, $note);
            $message = __('Changes applied to :name.', ['name' => $changeRequest->member->full_name]);
        } else {
            $review->reject($request->user(), $changeRequest, $note);
            $message = __('Request rejected.');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
