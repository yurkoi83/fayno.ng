<?php

namespace App\Nova\Support;

class FaynoNovaUrl
{
    protected static function basePath(): string
    {
        return trim((string) config('nova.path', '/nova'), '/');
    }

    public static function home(): string
    {
        return url(static::basePath());
    }

    public static function resource(string $resource, int|string|null $id = null, ?string $action = null): string
    {
        $segments = [static::basePath(), 'resources', trim($resource, '/')];

        if ($id !== null) {
            $segments[] = rawurlencode((string) $id);
        }

        if ($action !== null && $action !== '') {
            $segments[] = trim($action, '/');
        }

        return url(implode('/', $segments));
    }
}
