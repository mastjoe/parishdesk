<?php

namespace App\Http\Controllers\Admin\People;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Support\People\MemberTable;
use App\Support\People\Occupations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberExportController extends Controller
{
    /**
     * Download the members matching the current search and filters as CSV.
     */
    public function __invoke(Request $request): StreamedResponse
    {
        Gate::authorize('export', Member::class);

        activity('people')->causedBy($request->user())->event('exported')
            ->withProperties(['search' => $request->query('search'), 'filter' => $request->query('filter')])
            ->log('Exported the member register');

        $query = MemberTable::for($request)->query();

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Member number', 'Title', 'First name', 'Middle name', 'Last name', 'Baptismal name', 'Gender',
                'Date of birth', 'Marital status', 'Wedding date', 'Phone', 'Other phone', 'WhatsApp number', 'Email',
                'Accepts messages', 'Address', 'City', 'State', 'Occupation', 'Emergency contact name',
                'Emergency contact phone', 'Emergency contact relationship', 'State of origin', 'LGA of origin', 'Community', 'Family', 'Family role', 'Status', 'Date joined',
            ], escape: '');

            $query->lazy(500)->each(function (Member $member) use ($out): void {
                fputcsv($out, array_map($this->cell(...), [
                    $member->member_no, $member->title, $member->first_name, $member->middle_name, $member->last_name,
                    $member->baptismal_name, $member->gender->label(), $member->date_of_birth?->toDateString(),
                    $member->marital_status?->label(), $member->wedding_date?->toDateString(), $member->phone,
                    $member->alt_phone, $member->whatsapp_phone, $member->email, $member->communication_consent ? 'Yes' : 'No',
                    $member->address, $member->city, $member->state, Occupations::label($member->occupation), $member->emergency_contact_name,
                    $member->emergency_contact_phone, $member->emergency_contact_relationship, $member->state_of_origin,
                    $member->lga_of_origin, $member->community?->name, $member->family?->name, $member->family_role?->label(),
                    $member->status->label(), $member->joined_at?->toDateString(),
                ]), escape: '');
            });

            fclose($out);
        }, 'members-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stop a spreadsheet from treating a cell as a formula.
     */
    private function cell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=@\t\r]|^[+\-](?![\d\s])/', $value) === 1 ? "'".$value : $value;
    }
}
