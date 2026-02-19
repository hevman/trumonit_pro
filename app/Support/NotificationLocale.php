<?php

namespace App\Support;

final class NotificationLocale
{
    public static function resolve(): string
    {
        $configured = trim((string) config('wids.notifications.locale', ''));

        if (in_array($configured, ['en', 'pl'], true)) {
            return $configured;
        }

        $appLocale = (string) app()->getLocale();

        if (in_array($appLocale, ['en', 'pl'], true)) {
            return $appLocale;
        }

        $fallback = (string) config('app.locale', 'en');

        return in_array($fallback, ['en', 'pl'], true) ? $fallback : 'en';
    }

    /**
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function run(callable $callback): mixed
    {
        $original = (string) app()->getLocale();
        $target = self::resolve();

        if ($target === $original) {
            return $callback();
        }

        app()->setLocale($target);

        try {
            return $callback();
        } finally {
            app()->setLocale($original);
        }
    }
}

