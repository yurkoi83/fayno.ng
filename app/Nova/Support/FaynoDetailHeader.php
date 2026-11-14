<?php

namespace App\\Nova\\Support;

class FaynoDetailHeader
{
    /**
     * Render the shared Blade partial used by all custom Nova detail pages.
     */
    public static function render(
        string $resourceLabel,
        string $resourceUrl,
        string $currentLabel,
        string $icon,
        string $title,
        string $subtitle,
        string $actions = ''
    ): string {
        return view('nova.partials.detail-header', compact(
            'resourceLabel',
            'resourceUrl',
            'currentLabel',
            'icon',
            'title',
            'subtitle',
            'actions'
        ))->render();
    }
}
