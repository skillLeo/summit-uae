<?php

namespace Tests\Feature;

use Tests\TestCase;

/** The database translation loader keeps Laravel's own lines (validation, auth, pagination, passwords). */
final class TranslationLoaderTest extends TestCase
{
    public function test_framework_validation_lines_are_translated(): void
    {
        foreach (['validation.required', 'auth.failed', 'pagination.next', 'passwords.reset'] as $key) {
            $this->assertNotSame($key, __($key, ['attribute' => 'email']), "{$key} has a text");
        }
        $this->assertSame('The email field is required.', __('validation.required', ['attribute' => 'email']));
    }
}
