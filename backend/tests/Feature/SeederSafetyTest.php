<?php

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

uses()->group('security');

function setSuperAdminPasswordEnv(?string $value): void
{
    if ($value === null) {
        putenv('SUPER_ADMIN_PASSWORD');
        unset($_ENV['SUPER_ADMIN_PASSWORD'], $_SERVER['SUPER_ADMIN_PASSWORD']);
    } else {
        putenv("SUPER_ADMIN_PASSWORD={$value}");
        $_ENV['SUPER_ADMIN_PASSWORD'] = $value;
        $_SERVER['SUPER_ADMIN_PASSWORD'] = $value;
    }
}

beforeEach(function () {
    $this->originalEnv = app()->environment();
    $this->originalPassword = env('SUPER_ADMIN_PASSWORD');
});

afterEach(function () {
    app()->detectEnvironment(fn () => $this->originalEnv);
    setSuperAdminPasswordEnv($this->originalPassword);
});

it('keeps a changed admin password when the seeder runs again', function () {
    setSuperAdminPasswordEnv(null);

    (new AdminUserSeeder)->run();

    $admin = User::where('email', 'admin@mis.com')->firstOrFail();
    $admin->forceFill(['password' => Hash::make('RotatedPassword456!')])->save();

    (new AdminUserSeeder)->run();

    $admin->refresh();
    expect(Hash::check('RotatedPassword456!', $admin->password))->toBeTrue();
});

it('refuses to seed the admin in production without SUPER_ADMIN_PASSWORD', function () {
    app()->detectEnvironment(fn () => 'production');
    setSuperAdminPasswordEnv(null);

    expect(fn () => (new AdminUserSeeder)->run())
        ->toThrow(RuntimeException::class, 'SUPER_ADMIN_PASSWORD');
});

it('does not seed demo users in production', function () {
    app()->detectEnvironment(fn () => 'production');
    setSuperAdminPasswordEnv('ProductionAdmin!234');

    (new DatabaseSeeder)->run();

    expect(User::where('email', 'admin@mis.com')->exists())->toBeTrue()
        ->and(User::where('email', 'ana.demo@mis.com')->exists())->toBeFalse()
        ->and(User::where('email', 'mentor.bienestar@mis.com')->exists())->toBeFalse();
});
