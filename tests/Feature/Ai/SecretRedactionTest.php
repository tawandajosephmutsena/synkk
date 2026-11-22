<?php

use App\Services\SecretScannerService;

test('SecretScannerService redacts common secrets while preserving markdown structure', function () {
    $scanner = app(SecretScannerService::class);

    $awsKey = 'AKIA'.'IOSFODNN7EXAMPLE';
    $githubPat = 'ghp_'.str_repeat('12ab', 9);
    $slackToken = 'xoxb'.'-123456789012-1234567890123-abcdefghij';
    $openAiKey = 'sk-proj-'.str_repeat('ab12', 9);
    $anthropicKey = 'sk-ant-api03-'.str_repeat('cd34', 9);
    $synkkToken = 'synkk_'.str_repeat('AB12', 10);
    $stripeKey = 'sk_live_'.str_repeat('98zy', 6);
    $pemHeader = '-----BEGIN RSA '.'PRIVATE KEY-----';
    $pemFooter = '-----END RSA '.'PRIVATE KEY-----';

    $markdown = <<<MD
# Ops Runbook
Connects to [[Architecture/Overview]] and [[Security/Keys]].

- AWS Key: {$awsKey}
- GitHub PAT: {$githubPat}
- Slack Token: {$slackToken}
- OpenAI Key: {$openAiKey}
- Anthropic Key: {$anthropicKey}
- Synkk Token: {$synkkToken}
- Stripe Key: {$stripeKey}
- api_key = "custom_secret_value_9876543210"

```pem
{$pemHeader}
MIIEowIBAAKCAQEAsomebase64contentline1
MIIEowIBAAKCAQEAsomebase64contentline2
{$pemFooter}
```
MD;

    $result = $scanner->redact($markdown);

    expect($result['count'])->toBe(9)
        ->and($result['detected'])->toContain('AWS Access Key ID')
        ->and($result['detected'])->toContain('Anthropic API Key')
        ->and($result['detected'])->toContain('OpenAI API Key')
        ->and($result['detected'])->toContain('Synkk Device Token')
        ->and($result['detected'])->toContain('PEM Private Key Block')
        ->and($result['redacted'])->toContain('[[Architecture/Overview]]')
        ->and($result['redacted'])->toContain('[[Security/Keys]]')
        ->and($result['redacted'])->toContain('api_key = "[REDACTED:GENERIC_API_KEY_HEADER]"')
        ->and($result['redacted'])->not->toContain($awsKey)
        ->and($result['redacted'])->not->toContain($slackToken)
        ->and($result['redacted'])->not->toContain('1234567890123-abcdefghij')
        ->and($result['redacted'])->not->toContain($githubPat)
        ->and($result['redacted'])->not->toContain('sk-ant-api03-')
        ->and($result['redacted'])->not->toContain('sk-proj-')
        ->and($result['redacted'])->not->toContain($synkkToken)
        ->and($result['redacted'])->not->toContain($stripeKey)
        ->and($result['redacted'])->not->toContain('MIIEowIBAAKCAQEAsomebase64contentline1');
});

test('SecretScannerService leaves normal markdown notes untouched', function () {
    $scanner = app(SecretScannerService::class);
    $note = "# Weekly Meeting\nDiscussed [[Roadmap 2026]] and performance tuning with team.";

    $scan = $scanner->scan($note);
    $redacted = $scanner->redact($note);

    expect($scan['has_secrets'])->toBeFalse()
        ->and($scan['detected'])->toBeEmpty()
        ->and($redacted['count'])->toBe(0)
        ->and($redacted['redacted'])->toBe($note);
});
