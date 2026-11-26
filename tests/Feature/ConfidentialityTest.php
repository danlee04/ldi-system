<?php

use App\Models\User;
use Livewire\Livewire;

test('a person who has not agreed is sent to the statement from every page', function () {
    $user = User::factory()->withoutConfidentialityAgreement()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('confidentiality'));
    $this->actingAs($user)->get(route('my-pds'))->assertRedirect(route('confidentiality'));
    $this->actingAs($user)->get(route('profile.edit'))->assertRedirect(route('confidentiality'));

    $this->actingAs($user)->get(route('confidentiality'))
        ->assertOk()
        ->assertSee('Republic Act No. 10173')
        ->assertSee('I have read the Statement of Confidentiality')
        // Signed in, so it times out like every other signed-in page.
        ->assertSee('<input type="hidden" name="reason" value="idle">', false);
});

test('the sign-in page carries no idle sign-out, since nobody is signed in yet', function () {
    $this->get(route('login'))->assertDontSee('<input type="hidden" name="reason" value="idle">', false);
});

test('the box has to be ticked before anything is recorded', function () {
    $user = User::factory()->withoutConfidentialityAgreement()->create();

    Livewire::actingAs($user)
        ->test('pages::confidentiality')
        ->call('agree')
        ->assertHasErrors(['agreed' => 'accepted']);

    expect($user->fresh()->confidentiality_accepted_at)->toBeNull();
});

test('agreeing is recorded once and the person goes on to where they were headed', function () {
    $user = User::factory()->withoutConfidentialityAgreement()->create();

    $this->actingAs($user)->get(route('my-pds'))->assertRedirect(route('confidentiality'));

    Livewire::actingAs($user)
        ->test('pages::confidentiality')
        ->set('agreed', true)
        ->call('agree')
        ->assertHasNoErrors()
        ->assertRedirect(route('my-pds'));

    expect($user->fresh()->confidentiality_accepted_at)->not->toBeNull();

    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
});

test('someone who already agreed is never shown the statement again', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $this->actingAs($user)->get(route('confidentiality'))->assertRedirect(route('dashboard'));
});
