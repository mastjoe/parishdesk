<?php

use App\Enums\Permission;
use App\Models\ParishSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

test('the parish profile can be updated', function () {
    $user = User::factory()->withPermissions(Permission::SettingsParishUpdate)->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.parish.update'), [
            'name' => 'St. Joseph Catholic Church',
            'diocese' => 'Archdiocese of Lagos',
            'country' => 'NG',
            'email' => 'office@stjoseph.test',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.settings.parish.edit'));

    expect(ParishSetting::current())
        ->name->toBe('St. Joseph Catholic Church')
        ->diocese->toBe('Archdiocese of Lagos');
});

test('the parish profile requires a name', function () {
    $user = User::factory()->withPermissions(Permission::SettingsParishUpdate)->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.parish.update'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('settings survive a cache store that refuses to unserialize objects', function () {
    config(['cache.default' => 'database']);
    Cache::setDefaultDriver('database');

    ParishSetting::current()->update(['name' => 'Holy Trinity']);

    expect(ParishSetting::current()->name)->toBe('Holy Trinity')
        ->and(ParishSetting::current()->name)->toBe('Holy Trinity');
});

test('a preset theme is written on the page', function () {
    ParishSetting::current()->update(['theme' => 'martyr']);

    $this->get('/')
        ->assertOk()
        ->assertSee('data-theme="martyr"', escape: false)
        ->assertDontSee('id="parish-theme"', escape: false);
});

test('a custom colour is saved and rendered as theme css', function () {
    $user = User::factory()->withPermissions(Permission::SettingsAppearanceUpdate)->create();

    $this->actingAs($user)
        ->post(route('admin.settings.appearance.update'), ['theme' => 'custom', 'custom_color' => '#2E7D4F'])
        ->assertSessionHasNoErrors();

    expect(ParishSetting::current()->custom_color)->toBe('#2e7d4f');

    $this->get('/')
        ->assertSee('data-theme="custom"', escape: false)
        ->assertSee(':root[data-theme="custom"]{--primary:oklch(', escape: false);
});

test('appearance input is validated', function (array $payload, string $field) {
    $user = User::factory()->withPermissions(Permission::SettingsAppearanceUpdate)->create();

    $this->actingAs($user)
        ->post(route('admin.settings.appearance.update'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'unknown theme' => [['theme' => 'neon'], 'theme'],
    'custom without colour' => [['theme' => 'custom'], 'custom_color'],
    'colour that could inject css' => [['theme' => 'custom', 'custom_color' => 'red;}body{display:none'], 'custom_color'],
]);

test('a logo can be uploaded and replaced', function () {
    Storage::fake('public');
    $user = User::factory()->withPermissions(Permission::SettingsAppearanceUpdate)->create();

    $this->actingAs($user)->post(route('admin.settings.appearance.update'), [
        'theme' => 'marian',
        'logo' => UploadedFile::fake()->image('logo.png', 256, 256),
    ]);
    $first = ParishSetting::current()->logo_path;

    $this->post(route('admin.settings.appearance.update'), [
        'theme' => 'marian',
        'logo' => UploadedFile::fake()->image('new.png', 256, 256),
    ]);

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists(ParishSetting::current()->logo_path);
});

test('finance defaults are validated and saved', function () {
    $user = User::factory()->withPermissions(Permission::SettingsFinanceUpdate)->create();

    $this->actingAs($user)
        ->patch(route('admin.settings.finance.update'), [
            'currency' => 'GHS',
            'timezone' => 'Not/AZone',
            'fiscal_year_start_month' => 13,
        ])
        ->assertSessionHasErrors(['timezone', 'fiscal_year_start_month']);

    $this->patch(route('admin.settings.finance.update'), [
        'currency' => 'GHS',
        'timezone' => 'Africa/Accra',
        'fiscal_year_start_month' => 7,
        'bank_name' => 'Parish Bank',
    ])->assertSessionHasNoErrors();

    expect(ParishSetting::current())
        ->currency->toBe('GHS')
        ->fiscal_year_start_month->toBe(7);
});
