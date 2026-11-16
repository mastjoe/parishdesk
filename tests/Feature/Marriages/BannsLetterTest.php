<?php

use App\Enums\BannsLetter;
use App\Enums\ClergyRole;
use App\Enums\Permission;
use App\Models\BannsObjection;
use App\Models\ClergyAssignment;
use App\Models\MarriageApplication;
use App\Models\User;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Capture the HTML of the letter as it is handed to the PDF renderer.
 */
function captureBannsLetter(): Closure
{
    $html = null;

    View::composer('pdf.marriages.banns-letter', function ($view) use (&$html): void {
        $html = fn () => $view->render();
    });

    return function () use (&$html): string {
        expect($html)->not->toBeNull('The banns letter view was not rendered.');

        return $html();
    };
}

function marriageViewer(): User
{
    return User::factory()->withPermissions(Permission::MarriagesView)->create();
}

test('a banns request is written to the parish of the groom', function () {
    $this->travelTo('2026-11-16');
    $application = MarriageApplication::factory()->documentsVerified()->create([
        'application_no' => 'MAR/2026/004',
        'groom_name' => 'Chidi Okeke',
        'groom_father_name' => 'Emeka Okeke',
        'groom_mother_name' => 'Ada Okeke',
        'groom_parish' => 'St. Mary, Onitsha',
        'bride_name' => 'Ngozi Eze',
        'proposed_date' => '2027-02-13',
    ]);
    ClergyAssignment::factory()->create(['name' => 'Rev. Fr. Paul Obi', 'role' => ClergyRole::ParishPriest]);
    $html = captureBannsLetter();

    $response = $this->actingAs(marriageViewer())
        ->get(route('admin.marriages.letters.show', [$application, BannsLetter::GroomParishRequest]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'inline; filename=MAR-2026-004-groom-parish-request.pdf');
    expect($html())
        ->toContain('Request for the Publication of Banns')
        ->toContain('St. Mary, Onitsha')
        ->toContain('son of Emeka Okeke and Ada Okeke')
        ->toContain('13 February 2027')
        ->toContain('16 November 2026')
        ->toContain('Rev. Fr. Paul Obi')
        ->toContain('As the groom belongs to your parish');
});

test('the banns confirmation lists the dates of the three publications', function () {
    $this->travelTo('2026-11-16');
    $application = MarriageApplication::factory()->cleared()->create(['wedding_parish' => 'Holy Trinity, Lagos']);

    $html = captureBannsLetter();

    $this->actingAs(marriageViewer())
        ->get(route('admin.marriages.letters.show', [$application, BannsLetter::Confirmation]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($html())
        ->toContain('Certificate of Banns')
        ->toContain('Holy Trinity, Lagos')
        ->toContain('on 26 October 2026, 2 November 2026 and 9 November 2026.');
});

test('a letter is not written before the application is ready for it', function (Closure $application, BannsLetter $letter) {
    $this->actingAs(marriageViewer())
        ->get(route('admin.marriages.letters.show', [$application(), $letter]))
        ->assertNotFound();
})->with([
    'a request with no other parish' => [fn () => MarriageApplication::factory()->documentsVerified()->create(['bride_parish' => 'St. Mary, Onitsha']), BannsLetter::GroomParishRequest],
    'a request before the documents are verified' => [fn () => MarriageApplication::factory()->checked()->create(['groom_parish' => 'St. Mary, Onitsha']), BannsLetter::GroomParishRequest],
    'a request after the wedding' => [fn () => MarriageApplication::factory()->cleared()->create(['groom_parish' => 'St. Mary, Onitsha', 'status' => 'married']), BannsLetter::GroomParishRequest],
    'a confirmation for a wedding held here' => [fn () => MarriageApplication::factory()->cleared()->create(), BannsLetter::Confirmation],
    'a confirmation before the couple is cleared' => [fn () => MarriageApplication::factory()->withBanns()->create(['wedding_parish' => 'Holy Trinity, Lagos']), BannsLetter::Confirmation],
    'a confirmation with an open objection' => [fn () => BannsObjection::factory()->for(MarriageApplication::factory()->cleared()->state(['wedding_parish' => 'Holy Trinity, Lagos']), 'application')->create()->application, BannsLetter::Confirmation],
]);

test('only users who can view marriages can download the letters', function () {
    $application = MarriageApplication::factory()->cleared()->create(['wedding_parish' => 'Holy Trinity, Lagos']);

    $this->actingAs(User::factory()->withPermissions(Permission::MembersView)->create())
        ->get(route('admin.marriages.letters.show', [$application, BannsLetter::Confirmation]))
        ->assertForbidden();
});

test('the application page offers the letters that can be written now', function () {
    $application = MarriageApplication::factory()->cleared()->create([
        'groom_parish' => null,
        'bride_parish' => 'St. Mary, Onitsha',
        'wedding_parish' => 'Holy Trinity, Lagos',
    ]);

    $this->actingAs(marriageViewer())
        ->get(route('admin.marriages.show', $application))
        ->assertInertia(fn (Assert $page) => $page->where('application.letters', [
            ['value' => 'bride-parish-request', 'label' => 'Banns request to the bride\'s parish', 'addressee' => 'St. Mary, Onitsha'],
            ['value' => 'confirmation', 'label' => 'Banns confirmation to the wedding parish', 'addressee' => 'Holy Trinity, Lagos'],
        ]));
});
