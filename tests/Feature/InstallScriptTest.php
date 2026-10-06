<?php

/**
 * @return array{root: string, source: string, install: string, bin: string}
 */
function makeInstallScriptFixture(): array
{
    $root = sys_get_temp_dir().'/synkk-install-test-'.bin2hex(random_bytes(8));
    $source = $root.'/source';
    $bin = $root.'/bin';

    mkdir($source.'/docker', 0700, true);
    mkdir($bin, 0700, true);
    copy(__DIR__.'/../../install.sh', $source.'/install.sh');

    foreach ([
        'Dockerfile' => "FROM scratch\n",
        'composer.json' => "{}\n",
        'package.json' => "{}\n",
        'docker/entrypoint.sh' => "#!/bin/sh\n",
        'docker/Caddyfile' => "{\n    email {\$CADDY_EMAIL}\n}\n{\$SYNKK_DOMAIN} {\n    reverse_proxy synkk:8080\n}\n",
        '.dockerignore' => ".env\n.env.*\n",
        '.env.local' => "SECRET_SENTINEL=do-not-copy\n",
    ] as $path => $contents) {
        file_put_contents($source.'/'.$path, $contents);
    }

    file_put_contents($bin.'/id', <<<'SH'
#!/bin/sh
[ "$1" = -u ] || exit 64
echo 0
SH);
    file_put_contents($bin.'/sleep', "#!/bin/sh\nexit 0\n");
    file_put_contents($bin.'/curl', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$MOCK_CURL_LOG"
case "$*" in
    *https://vault.example.test/up*)
        [ "$MOCK_HTTPS" = ready ]
        exit $?
        ;;
esac
exit 64
SH);
    file_put_contents($bin.'/docker', <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$MOCK_DOCKER_LOG"
case "$1" in
    info) exit 0 ;;
    compose)
        [ "$2" = version ] && exit 0
        [ "$2" = -f ] || exit 64
        case "$4" in
            config|up|exec) exit 0 ;;
            ps) echo app-container; exit 0 ;;
        esac
        ;;
    inspect) echo healthy; exit 0 ;;
    logs) echo 'Administrator [admin@example.test] provisioned successfully.'; exit 0 ;;
esac
exit 64
SH);

    foreach (['id', 'sleep', 'curl', 'docker'] as $command) {
        chmod($bin.'/'.$command, 0700);
    }

    return compact('root', 'source', 'bin') + ['install' => $root.'/install'];
}

/**
 * @param  array{root: string, source: string, install: string, bin: string}  $fixture
 * @param  array<string, string>  $overrides
 * @return array{exit: int, stdout: string, stderr: string}
 */
function runInstallScriptFixture(array $fixture, array $overrides = []): array
{
    $environment = array_merge(getenv(), [
        'PATH' => $fixture['bin'].':'.getenv('PATH'),
        'INSTALL_DIR' => $fixture['install'],
        'SYNKK_DOMAIN' => 'vault.example.test',
        'SYNKK_ADMIN_EMAIL' => 'admin@example.test',
        'SYNKK_ADMIN_PASSWORD' => 'SecureInstallPassword123!',
        'SYNKK_ADMIN_NAME' => 'Test Admin',
        'MOCK_HTTPS' => 'ready',
        'MOCK_DOCKER_LOG' => $fixture['root'].'/docker.log',
        'MOCK_CURL_LOG' => $fixture['root'].'/curl.log',
    ], $overrides);

    $process = proc_open(['/bin/bash', $fixture['source'].'/install.sh'], [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $fixture['root'], $environment);

    if (! is_resource($process)) {
        throw new RuntimeException('Could not start the installer fixture.');
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return ['exit' => proc_close($process), 'stdout' => $stdout, 'stderr' => $stderr];
}

function removeInstallScriptFixture(string $root): void
{
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($root);
}

it('creates a fresh HTTPS installation without copying local secrets into the build source', function () {
    $fixture = makeInstallScriptFixture();

    try {
        $result = runInstallScriptFixture($fixture);

        expect($result['exit'])->toBe(0);
        expect($result['stdout'])->toContain('Synkk is online at https://vault.example.test')
            ->toContain('Initial administrator password: SecureInstallPassword123!');
        expect(file_exists($fixture['install'].'/.env.local'))->toBeFalse();
        expect(file_get_contents($fixture['install'].'/.env.production'))
            ->toContain('APP_URL=https://vault.example.test')
            ->toContain('SYNKK_BOOTSTRAP_PASSWORD="SecureInstallPassword123!"');
        expect(fileperms($fixture['install'].'/.env.production') & 0777)->toBe(0600);
        expect(file_get_contents($fixture['install'].'/docker-compose.install.yml'))
            ->toContain('synkk_storage:/var/www/html/storage')
            ->not->toContain('/var/www/html/database');
        expect(file_get_contents($fixture['install'].'/docker/Caddyfile.install'))
            ->toContain('reverse_proxy app:80')
            ->not->toContain('synkk:8080');
        expect(file_get_contents($fixture['root'].'/docker.log'))
            ->toContain('synkk:bootstrap-admin --if-empty --no-interaction')
            ->not->toContain('SecureInstallPassword123!');
    } finally {
        removeInstallScriptFixture($fixture['root']);
    }
});

it('preserves generated secrets and user data when rerun', function () {
    $fixture = makeInstallScriptFixture();

    try {
        expect(runInstallScriptFixture($fixture)['exit'])->toBe(0);
        $originalEnvironment = file_get_contents($fixture['install'].'/.env.production');
        mkdir($fixture['install'].'/storage/app', 0700, true);
        file_put_contents($fixture['install'].'/storage/app/user-note.md', 'keep this note');

        $result = runInstallScriptFixture($fixture, [
            'SYNKK_DOMAIN' => 'different.example.test',
            'SYNKK_ADMIN_PASSWORD' => 'DifferentInstallPassword456!',
        ]);

        expect($result['exit'])->toBe(0);
        expect($result['stdout'])->toContain('Use the existing administrator credentials.')
            ->not->toContain('Initial administrator password:');
        expect(file_get_contents($fixture['install'].'/.env.production'))->toBe($originalEnvironment);
        expect(file_get_contents($fixture['install'].'/storage/app/user-note.md'))->toBe('keep this note');
        expect(substr_count(file_get_contents($fixture['root'].'/docker.log'), 'synkk:bootstrap-admin --if-empty --no-interaction'))->toBe(2);
    } finally {
        removeInstallScriptFixture($fixture['root']);
    }
});

it('fails clearly without announcing success or printing credentials when HTTPS is unavailable', function () {
    $fixture = makeInstallScriptFixture();

    try {
        $result = runInstallScriptFixture($fixture, ['MOCK_HTTPS' => 'unavailable']);

        expect($result['exit'])->toBe(1);
        expect($result['stderr'])->toContain('HTTPS is not ready for vault.example.test');
        expect($result['stdout'])->not->toContain('Synkk is online')
            ->not->toContain('Initial administrator password:');
    } finally {
        removeInstallScriptFixture($fixture['root']);
    }
});
