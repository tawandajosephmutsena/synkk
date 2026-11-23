<?php

test('session cookies default to secure in production', function () {
    $originalAppEnvironment = getenv('APP_ENV');
    $originalSecureCookie = getenv('SESSION_SECURE_COOKIE');
    $originalServerAppEnvironment = $_SERVER['APP_ENV'] ?? null;
    $originalEnvironmentAppEnvironment = $_ENV['APP_ENV'] ?? null;
    $originalServerSecureCookie = $_SERVER['SESSION_SECURE_COOKIE'] ?? null;
    $originalEnvironmentSecureCookie = $_ENV['SESSION_SECURE_COOKIE'] ?? null;

    putenv('APP_ENV=production');
    putenv('SESSION_SECURE_COOKIE');
    $_SERVER['APP_ENV'] = 'production';
    $_ENV['APP_ENV'] = 'production';
    unset($_SERVER['SESSION_SECURE_COOKIE'], $_ENV['SESSION_SECURE_COOKIE']);

    try {
        $sessionConfiguration = require config_path('session.php');

        expect($sessionConfiguration['secure'])->toBeTrue();
    } finally {
        putenv($originalAppEnvironment === false ? 'APP_ENV' : "APP_ENV={$originalAppEnvironment}");
        putenv($originalSecureCookie === false ? 'SESSION_SECURE_COOKIE' : "SESSION_SECURE_COOKIE={$originalSecureCookie}");

        if ($originalServerAppEnvironment === null) {
            unset($_SERVER['APP_ENV']);
        } else {
            $_SERVER['APP_ENV'] = $originalServerAppEnvironment;
        }

        if ($originalEnvironmentAppEnvironment === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $originalEnvironmentAppEnvironment;
        }

        if ($originalServerSecureCookie === null) {
            unset($_SERVER['SESSION_SECURE_COOKIE']);
        } else {
            $_SERVER['SESSION_SECURE_COOKIE'] = $originalServerSecureCookie;
        }

        if ($originalEnvironmentSecureCookie === null) {
            unset($_ENV['SESSION_SECURE_COOKIE']);
        } else {
            $_ENV['SESSION_SECURE_COOKIE'] = $originalEnvironmentSecureCookie;
        }
    }
});
