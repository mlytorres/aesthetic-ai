<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    Plan::factory()->create(['slug' => 'starter']);

    $response = $this->post(route('register.store'), [
        'clinic_name' => 'Test Clinic',
        'slug' => 'test-clinic',
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secret123!A',
        'password_confirmation' => 'secret123!A',
    ]);

    // User is created and authenticated; Fortify redirects to home.
    // The email-verification wall is enforced when they actually try to access /dashboard.
    $this->assertAuthenticated();
    $response->assertRedirect('/dashboard');
});
