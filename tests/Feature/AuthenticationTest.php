<?php

use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to login when visiting horizon', function () {
    $this->get('/horizon')->assertRedirect('/login');
});

test('admins can log in and reach horizon', function () {
    $admin = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

    Livewire::test('pages::login')
        ->set('email', $admin->email)
        ->set('password', 'secret123')
        ->call('login')
        ->assertRedirect();

    $this->assertAuthenticatedAs($admin);
    $this->get('/horizon')->assertOk();
});

test('invalid credentials show an error and do not log in', function () {
    $admin = User::factory()->admin()->create(['password' => bcrypt('secret123')]);

    Livewire::test('pages::login')
        ->set('email', $admin->email)
        ->set('password', 'wrong-password')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
});

test('non-admin authenticated users cannot view horizon', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/horizon')->assertForbidden();
});
