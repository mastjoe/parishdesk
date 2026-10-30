<?php

namespace App\Http\Controllers\Admin\People;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Member;
use App\Support\People\MemberPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Small JSON searches behind the member and family pickers.
 */
class LookupController extends Controller
{
    /**
     * Find members by name, number or phone.
     */
    public function members(Request $request): JsonResponse
    {
        Gate::authorize('lookup', Member::class);

        $members = Member::query()->with(['family:id,name', 'media'])
            ->tap(fn (Builder $query) => $this->search($query, $request, ['first_name', 'middle_name', 'last_name', 'member_no', 'phone']))
            ->orderBy('last_name')->orderBy('first_name')
            ->limit(10)
            ->get()
            ->map(fn (Member $member): array => [
                ...MemberPresenter::summary($member),
                'family' => $member->family?->name,
            ]);

        return response()->json(['data' => $members]);
    }

    /**
     * Find families by name or address.
     */
    public function families(Request $request): JsonResponse
    {
        Gate::authorize('lookup', Family::class);

        $families = Family::query()->with('head')->withCount('members')
            ->tap(fn (Builder $query) => $this->search($query, $request, ['name', 'address']))
            ->orderBy('name')
            ->limit(10)
            ->get()
            ->map(fn (Family $family): array => [
                'id' => $family->id,
                'name' => $family->name,
                'head' => $family->head?->full_name,
                'membersCount' => $family->members_count,
                'communityId' => $family->community_id,
            ]);

        return response()->json(['data' => $families]);
    }

    /**
     * Require every word of the search to match one of the columns.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $columns
     */
    private function search(Builder $query, Request $request, array $columns): void
    {
        foreach (preg_split('/\s+/', trim((string) $request->string('q'))) ?: [] as $term) {
            if ($term !== '') {
                $query->whereAny($columns, 'like', '%'.addcslashes($term, '%_\\').'%');
            }
        }
    }
}
