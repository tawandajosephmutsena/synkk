<?php

test('the about page renders with full philosophy, four broken paradigms, and five pillars', function () {
    $response = $this->get(route('about'));

    $response
        ->assertOk()
        ->assertSee('About &amp; Philosophy — Synkk Obsidian Everywhere', escape: false)
        ->assertSee('The Synkk Philosophy')
        ->assertSee('Thinking is')
        ->assertSee('personal.')
        ->assertSee('Keep it yours.')
        ->assertSee('Why existing sync solutions break down for teams.')
        ->assertSee('The Cost &amp; Privacy Dilemma of Proprietary Sync', escape: false)
        ->assertSee('The Fragility of Community Git Plugins', escape: false)
        ->assertSee('The Data Exposure of Cloud Monoliths', escape: false)
        ->assertSee('The Mobile Storage Constraint', escape: false)
        ->assertSee('The foundational principles behind Synkk.')
        ->assertSee('Local-First &amp; 100% Sovereign', escape: false)
        ->assertSee('Team-Aware with Path-Scoped ACLs')
        ->assertSee('Safe &amp; Deterministic. Zero Silent Loss.', escape: false)
        ->assertSee('Intelligent &amp; Agentic on Your Hardware.', escape: false)
        ->assertSee('Zero-Knowledge Cryptography &amp; Fleet DLP.', escape: false)
        ->assertSee('Built upon a battle-tested, decoupled stack.')
        ->assertSee('PHP 8.5 / LARAVEL 12')
        ->assertSee('TYPESCRIPT / CM6')
        ->assertSee('Own your team brain today.')
        ->assertSee('synkk-sovereign-deploy.sh')
        ->assertSee('/images/synkk-logo.svg', escape: false);
});

test('the about page links to docs and home with matching branding', function () {
    $response = $this->get(route('about'));

    $response
        ->assertOk()
        ->assertSee('href="'.route('home').'"', escape: false)
        ->assertSee('href="'.route('public.docs').'"', escape: false)
        ->assertSee('class="scroll-smooth dark"', escape: false)
        ->assertSee('data-theme="dark"', escape: false);
});

test('the welcome page links to the about page in primary navigation, mobile menu, and footer', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('href="'.route('about').'"', escape: false)
        ->assertSee('About &amp; Philosophy', escape: false);
});

test('the documentation page links to the about page', function () {
    $response = $this->get(route('public.docs'));

    $response
        ->assertOk()
        ->assertSee('href="'.route('about').'"', escape: false);
});
