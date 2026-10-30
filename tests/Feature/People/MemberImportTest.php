<?php

use App\Enums\Gender;
use App\Enums\Permission;
use App\Models\Community;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('register.csv', $contents);
}

beforeEach(function () {
    Storage::fake('local');
    $this->importer = User::factory()->withPermissions(Permission::MembersImport)->create();
});

test('an uploaded file is previewed with suggested column matches', function () {
    $this->actingAs($this->importer)
        ->post(route('admin.members.import.upload'), ['file' => csvUpload("\xEF\xBB\xBFSurname;First Name;Sex;DOB\nOkafor;Grace;F;12/03/1990\n")])
        ->assertRedirect(route('admin.members.import.create'));

    $this->actingAs($this->importer)
        ->get(route('admin.members.import.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('upload.headers', ['Surname', 'First Name', 'Sex', 'DOB'])
            ->where('upload.rowCount', 1)
            ->where('upload.preview.0.Surname', 'Okafor')
            ->where('upload.suggested', [
                'first_name' => 'First Name',
                'last_name' => 'Surname',
                'gender' => 'Sex',
                'date_of_birth' => 'DOB',
            ]));
});

test('rows are imported, and invalid or duplicate rows are skipped with a reason', function () {
    $community = Community::factory()->create(['name' => 'St. Jude SCC']);
    Member::factory()->create(['first_name' => 'Peter', 'last_name' => 'Eze', 'date_of_birth' => '1985-07-01', 'email' => null]);

    $csv = implode("\n", [
        'First,Last,Gender,Birthday,Email,SCC,Reg No',
        'Grace,Okafor,female,12/03/1990,GRACE@example.com,st. jude scc,OLD-17',
        'Peter,Eze,M,1985-07-01,,,',
        'Ada,Obi,unknown,,,,',
        'John,Bosco,male,31/02/2001,,,',
        '',
        'Mary,Musa,F,,,Nowhere,',
    ]);

    $this->actingAs($this->importer)->post(route('admin.members.import.upload'), ['file' => csvUpload($csv)]);

    $this->actingAs($this->importer)
        ->post(route('admin.members.import.store'), ['mapping' => [
            'first_name' => 'First', 'last_name' => 'Last', 'gender' => 'Gender', 'date_of_birth' => 'Birthday',
            'email' => 'Email', 'community' => 'SCC', 'member_no' => 'Reg No',
        ]])
        ->assertRedirect(route('admin.members.import.create'))
        ->assertSessionHas('member_import_result', fn (array $result) => $result['created'] === 2
            && array_column($result['skipped'], 'line') === [3, 4, 5]
            && str_contains($result['skipped'][0]['reason'], 'Already in the register')
            && $result['skipped'][1]['reason'] === 'Gender must be male or female.');

    $grace = Member::query()->where('first_name', 'Grace')->sole();
    $mary = Member::query()->where('first_name', 'Mary')->sole();

    expect($grace->member_no)->toBe('OLD-17')
        ->and($grace->email)->toBe('grace@example.com')
        ->and($grace->date_of_birth->toDateString())->toBe('1990-03-12')
        ->and($grace->community_id)->toBe($community->id)
        ->and($mary->gender)->toBe(Gender::Female)
        ->and($mary->community_id)->toBeNull()
        ->and($mary->member_no)->toStartWith('MEM-')
        ->and(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('first name, last name and gender must be mapped to columns from the file', function () {
    $this->actingAs($this->importer)->post(route('admin.members.import.upload'), ['file' => csvUpload("First,Last,Gender\nGrace,Okafor,F\n")]);

    $this->actingAs($this->importer)
        ->post(route('admin.members.import.store'), ['mapping' => ['first_name' => 'First', 'last_name' => 'Surname']])
        ->assertSessionHasErrors(['mapping.gender', 'mapping.last_name']);

    expect(Member::query()->count())->toBe(0);
});

test('importing without an uploaded file asks for the file again', function () {
    $this->actingAs($this->importer)
        ->post(route('admin.members.import.store'), ['mapping' => ['first_name' => 'First', 'last_name' => 'Last', 'gender' => 'Gender']])
        ->assertSessionHasErrors('mapping.first_name');
});

test('a file without column names is rejected', function () {
    $this->actingAs($this->importer)
        ->post(route('admin.members.import.upload'), ['file' => csvUpload("\n\n")])
        ->assertSessionHasErrors('file');

    expect(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('starting over deletes the uploaded file', function () {
    $this->actingAs($this->importer)->post(route('admin.members.import.upload'), ['file' => csvUpload("First,Last,Gender\n")]);

    expect(Storage::disk('local')->allFiles('imports'))->toHaveCount(1);

    $this->actingAs($this->importer)->delete(route('admin.members.import.destroy'));

    expect(Storage::disk('local')->allFiles('imports'))->toBe([]);
});

test('importing needs the import permission', function () {
    $creator = User::factory()->withPermissions(Permission::MembersCreate)->create();

    $this->actingAs($creator)
        ->post(route('admin.members.import.upload'), ['file' => csvUpload("First,Last,Gender\n")])
        ->assertForbidden();
});
