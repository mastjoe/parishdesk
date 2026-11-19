<?php

use App\Enums\ClergyRole;
use App\Enums\Gender;
use App\Enums\Permission;
use App\Enums\SacramentType;
use App\Enums\SponsorRole;
use App\Models\ClergyAssignment;
use App\Models\MarriageApplication;
use App\Models\SacramentRecord;
use App\Models\User;
use Illuminate\Support\Facades\View;

/**
 * Capture the HTML of the certificate as it is handed to the PDF renderer.
 */
function captureMarriageCertificate(): Closure
{
    $html = null;

    View::composer('pdf.sacraments.marriage-certificate', function ($view) use (&$html): void {
        $html = fn () => $view->render();
    });

    return function () use (&$html): string {
        expect($html)->not->toBeNull('The marriage certificate view was not rendered.');

        return $html();
    };
}

function registerViewer(): User
{
    return User::factory()->withPermissions(Permission::SacramentsView)->create();
}

test('a marriage certificate is issued from the register entry of a wedding held here', function () {
    $this->travelTo('2026-11-19');
    $record = SacramentRecord::factory()->ofType(SacramentType::Matrimony)->create([
        'person_name' => 'Chidi Okeke',
        'gender' => Gender::Male,
        'spouse_name' => 'Ngozi Eze',
        'received_on' => '2026-10-31',
        'minister' => 'Rev. Fr. Michael Eze',
        'register_book' => '2',
        'register_page' => '41',
        'entry_no' => '815',
    ]);
    $record->sponsors()->createMany([
        ['name' => 'Paul Nnaji', 'role' => SponsorRole::Witness],
        ['name' => 'Grace Obi', 'role' => SponsorRole::Witness],
    ]);
    MarriageApplication::factory()->cleared()->create([
        'matrimony_record_id' => $record->id,
        'groom_father_name' => 'Emeka Okeke',
        'groom_mother_name' => 'Ada Okeke',
        'bride_father_name' => 'Uche Eze',
        'bride_parish' => 'Holy Ghost Cathedral, Enugu',
    ]);
    ClergyAssignment::factory()->create(['name' => 'Rev. Fr. Paul Obi', 'role' => ClergyRole::ParishPriest]);
    $html = captureMarriageCertificate();

    $this->actingAs(registerViewer())
        ->get(route('admin.sacraments.certificate', $record))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', "inline; filename=certificate-of-marriage-{$record->id}.pdf");

    expect($html())
        ->toContain('Certificate of Marriage')
        ->toMatch('/Husband<\/th>\s*<td>\s*Chidi Okeke\s*<br>son of Emeka Okeke and Ada Okeke/')
        ->toMatch('/Wife<\/th>\s*<td>\s*Ngozi Eze\s*<br>daughter of Uche Eze\s*<br>Parish: Holy Ghost Cathedral, Enugu/')
        ->toContain('united in Holy Matrimony on 31 October 2026')
        ->toContain('Rev. Fr. Michael Eze')
        ->toContain('Paul Nnaji and Grace Obi')
        ->toContain('Book 2, page 41, entry 815')
        ->toContain('Issued 19 November 2026')
        ->toContain('Rev. Fr. Paul Obi');
});

test('a wedding written in the register for the bride and held elsewhere is certified as such', function () {
    $record = SacramentRecord::factory()->ofType(SacramentType::Matrimony)->elsewhere('Holy Trinity, Lagos')->create([
        'person_name' => 'Ngozi Eze',
        'gender' => Gender::Female,
        'spouse_name' => 'Chidi Okeke',
    ]);
    $html = captureMarriageCertificate();

    $this->actingAs(registerViewer())->get(route('admin.sacraments.certificate', $record))->assertOk();

    expect($html())
        ->toMatch('/Husband<\/th>\s*<td>\s*Chidi Okeke\s*<\/td>/')
        ->toMatch('/Wife<\/th>\s*<td>\s*Ngozi Eze\s*<\/td>/')
        ->toMatch('/Place<\/th>\s*<td>Holy Trinity, Lagos<\/td>/');
});

test('no certificate is issued yet for the other sacraments', function () {
    $record = SacramentRecord::factory()->ofType(SacramentType::Baptism)->create();

    $this->actingAs(registerViewer())->get(route('admin.sacraments.certificate', $record))->assertNotFound();
});
