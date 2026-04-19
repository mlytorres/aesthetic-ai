<?php

declare(strict_types=1);

namespace App\Support;

final class SentryPhiScrubber
{
    public static function beforeSend(mixed $event): mixed
    {
        if (is_object($event) && method_exists($event, 'getRequest') && method_exists($event, 'setRequest')) {
            $request = $event->getRequest();

            if (is_array($request)) {
                $event->setRequest(self::scrubArray($request));
            }
        }

        if (is_object($event) && method_exists($event, 'getUser') && method_exists($event, 'setUser')) {
            $user = $event->getUser();

            if (is_array($user)) {
                unset($user['email'], $user['username'], $user['ip_address']);
                $event->setUser($user);
            }
        }

        if (is_object($event) && method_exists($event, 'getContexts') && method_exists($event, 'setContexts')) {
            $contexts = $event->getContexts();

            if (is_array($contexts)) {
                $event->setContexts(self::scrubArray($contexts));
            }
        }

        if (is_object($event) && method_exists($event, 'getExtra') && method_exists($event, 'setExtra')) {
            $extra = $event->getExtra();

            if (is_array($extra)) {
                $event->setExtra(self::scrubArray($extra));
            }
        }

        return $event;
    }

    public static function beforeBreadcrumb(mixed $breadcrumb): mixed
    {
        if (! is_object($breadcrumb) || ! method_exists($breadcrumb, 'getData') || ! method_exists($breadcrumb, 'setData')) {
            return $breadcrumb;
        }

        $data = $breadcrumb->getData();

        if (! is_array($data)) {
            return $breadcrumb;
        }

        $breadcrumb->setData(self::scrubArray($data));

        return $breadcrumb;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function scrubArray(array $payload): array
    {
        $sensitive = [
            'password',
            'password_confirmation',
            'current_password',
            'email',
            'phone',
            'first_name',
            'last_name',
            'name',
            'dob',
            'date_of_birth',
            'address',
            'token',
            'authorization',
            'cookie',
            'x-api-key',
            'medical_history',
            'quiz_answers',
            'consent',
        ];

        foreach ($payload as $key => $value) {
            $normalized = strtolower((string) $key);

            foreach ($sensitive as $needle) {
                if (str_contains($normalized, $needle)) {
                    $payload[$key] = '[REDACTED]';

                    continue 2;
                }
            }

            if (is_array($value)) {
                $payload[$key] = self::scrubArray($value);
            }
        }

        return $payload;
    }
}
