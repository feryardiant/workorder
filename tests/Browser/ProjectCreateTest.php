<?php

declare(strict_types=1);

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows a customer added from the create project flyout without reloading', function () {
    $owner = browserMember(Role::Owner);

    actingAs($owner);

    visit(route('projects.index'))
        ->assertSee(__('Projects'))
        ->click(__('Add project'))
        ->wait(0.4)
        ->click(__('Add customer'))
        ->wait(0.4)
        ->fill('dialog[open] input[role="combobox"][aria-label="'.__('Name').'"]', 'Flyout Customer')
        ->wait(0.5)
        ->click('dialog[open] button[type="submit"]')
        ->wait(1.2)
        ->assertScript("document.querySelector('dialog[open] [role=combobox][aria-label=\"".__('Customer')."\"]').value === 'Flyout Customer'")
        ->assertScript("!document.querySelector('dialog[open]').textContent.includes('".__('Add customer')."')");

    assertNoBackendErrors();
});
