<?php

test('the landing page explains the complete Obsidian sync workflow', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSee('Every sync has a checkpoint.')
        ->assertSeeInOrder([
            'Prepare the change',
            'Check the boundary',
            'Fingerprint the file',
            'Deliver to devices',
        ])
        ->assertSee('PATH RULES');
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
        ->assertSee('/images/synkk-logo.svg', escape: false)
        ->assertSee('Synkk — Obsidian everywhere')
        ->assertSee('/images/showcase/dashboard-overview.webp', escape: false)
        ->assertSee('/images/showcase/editor-full.webp', escape: false)
        ->assertSee('/images/showcase/graph-full.webp', escape: false)
        ->assertSee('/images/showcase/permissions-full.webp', escape: false)
        ->assertSee('/build/assets/landing-', escape: false)
        ->assertDontSee('fonts.bunny.net', escape: false)
        ->assertDontSee('dark:');
});

test('the landing page presents real product captures without a mascot overlay', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSee('Edit the note. Follow the graph.')
        ->assertSee('Four working surfaces.')
        ->assertSee('One vault.')
        ->assertSee('Markdown Editor')
        ->assertSee('Graph View')
        ->assertSee('See the vault at a glance.')
        ->assertSee('Set the boundary before you share.')
        ->assertSee('data-product-view="markdown-editor"', escape: false)
        ->assertSee('data-product-view="graph"', escape: false)
        ->assertSee('Real product capture')
        ->assertSee('Synkk dashboard showing vault health, connected devices, recent activity, and storage', escape: false)
        ->assertSee('/images/showcase/dashboard-overview.webp', escape: false)
        ->assertSee('/images/showcase/permissions-full.webp', escape: false)
        ->assertDontSee('/images/showcase/dashboard-full.png', escape: false)
        ->assertDontSee('/images/showcase/devices-full.png', escape: false)
        ->assertDontSee('synkk-editor-source', escape: false)
        ->assertDontSee('synkk-graph-canvas', escape: false);

    $publicAssets = [
        public_path('images/synkk-logo.svg'),
        public_path('images/showcase/dashboard-overview.webp'),
        public_path('images/showcase/editor-full.webp'),
        public_path('images/showcase/graph-full.webp'),
        public_path('images/showcase/permissions-full.webp'),
    ];

    foreach ($publicAssets as $publicAsset) {
        $this->assertFileExists($publicAsset);
        $this->assertGreaterThan(0, filesize($publicAsset));
    }

    $this->assertStringNotContainsString('synkk-logo-character-animated.svg', $response->getContent());
    $this->assertStringNotContainsString('synkk-workflow-mascot', $response->getContent());
    $this->assertFileDoesNotExist(public_path('images/showcase/dashboard-full.png'));
    $this->assertFileDoesNotExist(public_path('images/showcase/devices-full.png'));
});

test('the landing page presents the 1-year, lifetime, and enterprise meeting pricing options', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSee('$45')
        ->assertSee('1-year update license')
        ->assertSee('$65')
        ->assertSee('LIFETIME LICENSE')
        ->assertSee('ENTERPRISE & TEAMS')
        ->assertSee('book-it.ottomate.space', escape: false)
        ->assertSee('Book a meeting');
});

test('the landing page clearly separates the public plugin from the server launch roadmap', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSee('Obsidian plugin v1.0.0 is live on GitHub')
        ->assertSee('Plugin now. Server release next.')
        ->assertSee('Foundation server release')
        ->assertSee('Safety and collaboration')
        ->assertSee('Selective and private transport')
        ->assertSee('What can I install today?')
        ->assertSee('Is character-level CRDT sync available?')
        ->assertSee('synkk-reveal--two', escape: false)
        ->assertDontSee('Foundation beta is live on GitHub')
        ->assertDontSee('Deletion abort threshold set to ten percent')
        ->assertDontSee('Per device')
        ->assertDontSee('A calm control room for the whole vault.')
        ->assertDontSee('Foundation now. The hard sync problems next.')
        ->assertDontSee('Write the note. See the connections.')
        ->assertDontSee('official Community Plugins listing', escape: false);
});

test('the landing page withholds checkout until Lemon Squeezy is fully configured', function () {
    config()->set('synkk.lemon_squeezy.store_url', 'https://synkk.lemonsqueezy.com');
    config()->set('synkk.lemon_squeezy.store_id', '');
    config()->set('synkk.lemon_squeezy.product_id', '');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Checkout opens after launch checks')
        ->assertDontSee('href="https://synkk.lemonsqueezy.com"', escape: false)
        ->assertDontSee('Get Lifetime License');
});

test('the landing page links to checkout only when every Lemon Squeezy value is configured', function () {
    config()->set('synkk.lemon_squeezy.store_url', 'https://store.example.test/synkk-pro');
    config()->set('synkk.lemon_squeezy.store_id', '1234');
    config()->set('synkk.lemon_squeezy.product_id', '5678');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="https://store.example.test/synkk-pro"', escape: false)
        ->assertSee('Get Lifetime License')
        ->assertDontSee('Checkout opens after launch checks');
});

test('the landing page renders the comprehensive obsidian sync comparison matrix', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('What makes Synkk better than existing Obsidian sync tools?')
        ->assertSee('Team Standard')
        ->assertSee('Official Obsidian Sync')
        ->assertSee('Obsidian Git')
        ->assertSee('Remotely Save (S3/WebDAV)')
        ->assertSee('Self-Hosted LiveSync')
        ->assertSee('100% Self-Hosted')
        ->assertSee('Path-Level ACLs')
        ->assertSee('Data Loss Prevention (DLP)')
        ->assertSee('Atomic Safety Shield');
});
