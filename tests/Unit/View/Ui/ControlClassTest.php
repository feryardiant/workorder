<?php

declare(strict_types=1);

use Tests\TestCase;

it('forwards class to the control root', function (string $component) {
    /** @var TestCase $this */
    $html = (string) $this->blade("<x-ui.{$component} class=\"w-full sm:w-48\" />");

    // The class must land on the component's own root, not a child element.
    expect(preg_match('/\A<div\b[^>]*w-full sm:w-48/', $html))->toBe(1);
})->with([
    'autocomplete',
    'calendar',
    'date-picker',
    'datetime-picker',
    'file-upload',
    'select',
    'time-picker',
]);
