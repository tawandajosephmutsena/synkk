<?php

test('the landing page explains the complete Obsidian sync workflow', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSee('From local note to every trusted device')
        ->assertSeeInOrder([
            'Work in Obsidian',
            'Synkk verifies the change',
            'Your team receives the update',
        ])
        ->assertSee('Role and path rules stay attached to the vault');
});

test('the landing page presents every supported platform in a light experience', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSee('macOS')
        ->assertSee('Windows')
        ->assertSee('Linux')
        ->assertSee('iOS')
        ->assertSee('Android')
        ->assertSee('Obsidian')
        ->assertSee('/images/character/synkk-diver-premium.webp', escape: false)
        ->assertSee('/build/assets/landing-', escape: false)
        ->assertDontSee('fonts.bunny.net', escape: false)
        ->assertDontSee('dark:');
});
