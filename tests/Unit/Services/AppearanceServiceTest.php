<?php

use App\Enums\Appearance;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\User\AppearanceService;

function appearanceService(?UserRepositoryInterface $users = null): AppearanceService
{
    return new AppearanceService($users ?? Mockery::mock(UserRepositoryInterface::class));
}

it('uses the signed-in user preference over the cookie', function () {
    $user = new User(['appearance' => Appearance::Dark]);

    expect(appearanceService()->resolve($user, 'light'))->toBe(Appearance::Dark);
});

it('uses the cookie for guests', function (string $cookie, Appearance $expected) {
    expect(appearanceService()->resolve(null, $cookie))->toBe($expected);
})->with([
    ['light', Appearance::Light],
    ['dark', Appearance::Dark],
    ['system', Appearance::System],
]);

it('follows the system when there is no valid preference', function (mixed $cookie) {
    expect(appearanceService()->resolve(null, $cookie))->toBe(Appearance::System);
})->with([null, '', 'purple', [['dark']]]);

it('saves the preference through the user repository', function () {
    $user = new User;
    $users = Mockery::mock(UserRepositoryInterface::class);
    $users->shouldReceive('updateAppearance')->once()->with($user, Appearance::Dark)->andReturn($user);

    expect(appearanceService($users)->update($user, Appearance::Dark))->toBe($user);
});
