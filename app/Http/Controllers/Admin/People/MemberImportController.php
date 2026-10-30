<?php

namespace App\Http\Controllers\Admin\People;

use App\Actions\Members\ImportMembers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\People\ImportMembersRequest;
use App\Models\Member;
use App\Support\Import\CsvFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Imports members from a CSV file in three steps: upload the file, map its
 * columns to member fields while previewing the first rows, then import.
 *
 * The uploaded file waits on the private disk, and its location is kept in
 * the session, until it is imported or discarded.
 */
class MemberImportController extends Controller
{
    /**
     * Header spellings that commonly mean a member field.
     */
    private const array ALIASES = [
        'member_no' => ['memberno', 'membernumber', 'regno', 'registrationnumber', 'id'],
        'first_name' => ['firstname', 'givenname', 'forename'],
        'middle_name' => ['middlename', 'othername', 'othernames'],
        'last_name' => ['lastname', 'surname', 'familyname'],
        'baptismal_name' => ['baptismalname', 'christianname'],
        'gender' => ['gender', 'sex'],
        'date_of_birth' => ['dateofbirth', 'dob', 'birthday', 'birthdate'],
        'marital_status' => ['maritalstatus'],
        'wedding_date' => ['weddingdate', 'dateofmarriage', 'anniversary'],
        'phone' => ['phone', 'phonenumber', 'mobile', 'telephone', 'gsm'],
        'alt_phone' => ['altphone', 'otherphone', 'phone2', 'alternativephone'],
        'email' => ['email', 'emailaddress'],
        'address' => ['address', 'homeaddress', 'residentialaddress'],
        'state_of_origin' => ['stateoforigin', 'state'],
        'lga_of_origin' => ['lgaoforigin', 'lga'],
        'community' => ['community', 'scc', 'zone', 'station'],
        'joined_at' => ['datejoined', 'joined', 'joinedon', 'registeredon'],
    ];

    /**
     * Show the step the import is at.
     */
    public function create(Request $request): Response
    {
        Gate::authorize('import', Member::class);

        $file = $this->pendingUpload($request);

        return Inertia::render('admin/people/members/Import', [
            'fields' => collect(ImportMembers::FIELDS)
                ->map(fn (string $label, string $key): array => ['key' => $key, 'label' => $label, 'required' => in_array($key, ImportMembers::REQUIRED, true)])
                ->values(),
            'upload' => $file !== null ? $this->describe($file) : null,
            'result' => $request->session()->get('member_import_result'),
            'maxRows' => ImportMembers::MAX_ROWS,
        ]);
    }

    /**
     * Keep the uploaded file and read its columns.
     */
    public function upload(Request $request): RedirectResponse
    {
        Gate::authorize('import', Member::class);

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $this->discard($request);

        $path = $request->file('file')->store('imports', 'local');

        if ($path === false) {
            throw ValidationException::withMessages(['file' => __('The file could not be saved. Please try again.')]);
        }
        $headers = array_values(array_filter((new CsvFile(Storage::disk('local')->path($path)))->headers(), fn (string $header): bool => $header !== ''));

        if ($headers === []) {
            Storage::disk('local')->delete($path);

            throw ValidationException::withMessages(['file' => __('The first line of the file should hold the column names.')]);
        }

        $request->session()->put('member_import', [
            'path' => $path,
            'name' => $request->file('file')->getClientOriginalName(),
            'headers' => $headers,
        ]);

        return to_route('admin.members.import.create');
    }

    /**
     * Import the uploaded file with the chosen column mapping.
     */
    public function store(ImportMembersRequest $request, ImportMembers $importMembers): RedirectResponse
    {
        $upload = $this->pendingUpload($request);

        if ($upload === null) {
            throw ValidationException::withMessages(['file' => __('Upload the file again to continue.')]);
        }

        $result = $importMembers->handle(Storage::disk('local')->path($upload['path']), $request->mapping());

        $this->discard($request);

        activity('people')->causedBy($request->user())->event('imported')
            ->withProperties(['file' => $upload['name'], 'created' => $result['created'], 'skipped' => count($result['skipped'])])
            ->log("Imported {$result['created']} members from {$upload['name']}");

        return to_route('admin.members.import.create')->with('member_import_result', [...$result, 'fileName' => $upload['name']]);
    }

    /**
     * Throw away the uploaded file and start again.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Gate::authorize('import', Member::class);

        $this->discard($request);

        return to_route('admin.members.import.create');
    }

    /**
     * Describe the uploaded file for the mapping step.
     *
     * @param  array{path: string, name: string, headers: list<string>}  $upload
     * @return array<string, mixed>
     */
    private function describe(array $upload): array
    {
        $csv = new CsvFile(Storage::disk('local')->path($upload['path']));
        $allHeaders = $csv->headers();
        $preview = [];
        $count = 0;

        foreach ($csv->rows() as $row) {
            if ($count < 5) {
                $preview[] = collect($allHeaders)->mapWithKeys(fn (string $header, int $index) => [$header => $row[$index] ?? ''])->only($upload['headers'])->all();
            }

            $count++;
        }

        return [
            'fileName' => $upload['name'],
            'headers' => $upload['headers'],
            'preview' => $preview,
            'rowCount' => $count,
            'suggested' => $this->suggest($upload['headers']),
        ];
    }

    /**
     * Guess which column holds each member field from the header names.
     *
     * @param  list<string>  $headers
     * @return array<string, string>
     */
    private function suggest(array $headers): array
    {
        $normalised = collect($headers)->mapWithKeys(fn (string $header) => [Str::of($header)->lower()->replaceMatches('/[^a-z0-9]/', '')->value() => $header]);
        $suggested = [];

        foreach (ImportMembers::FIELDS as $field => $label) {
            $candidates = [str_replace('_', '', $field), Str::of($label)->lower()->replaceMatches('/[^a-z0-9]/', '')->value(), ...(self::ALIASES[$field] ?? [])];

            foreach ($candidates as $candidate) {
                if ($normalised->has($candidate) && ! in_array($normalised[$candidate], $suggested, true)) {
                    $suggested[$field] = $normalised[$candidate];
                    break;
                }
            }
        }

        return $suggested;
    }

    /**
     * Get the upload waiting in the session, if its file still exists.
     *
     * @return array{path: string, name: string, headers: list<string>}|null
     */
    private function pendingUpload(Request $request): ?array
    {
        $upload = $request->session()->get('member_import');

        if (! is_array($upload) || ! is_string($upload['path'] ?? null) || ! Storage::disk('local')->exists($upload['path'])) {
            return null;
        }

        return [
            'path' => $upload['path'],
            'name' => is_string($upload['name'] ?? null) ? $upload['name'] : 'members.csv',
            'headers' => array_values(array_filter((array) ($upload['headers'] ?? []), is_string(...))),
        ];
    }

    /**
     * Delete the uploaded file and forget it.
     */
    private function discard(Request $request): void
    {
        $upload = $request->session()->pull('member_import');

        if (is_array($upload) && isset($upload['path'])) {
            Storage::disk('local')->delete($upload['path']);
        }
    }
}
