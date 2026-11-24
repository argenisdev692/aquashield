<?php

declare(strict_types=1);

use Inertia\Testing\AssertableInertia as Assert;

it('renders the sign-in screen for a guest', function (): void {
    $this->get('/login')->assertInertia(
        fn (Assert $page) => $page
            ->component('Auth/Login')
            ->where('canResetPassword', true)
            ->where('status', null)
            // The demo block only exists on a local install; the testing
            // environment must never hand a visitor a working credential.
            ->where('demoCredentials', null),
    );
});
