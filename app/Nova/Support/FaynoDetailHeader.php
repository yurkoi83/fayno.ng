<?php

namespace App\Nova\Support;

class FaynoDetailHeader
{
    /**
     * Shared header for custom Nova detail pages.
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
        return '<header class="fayno-crm__heading">'
            . '<div class="fayno-crm__heading-main">'
            . FaynoBreadcrumbs::render($resourceLabel, $resourceUrl, $currentLabel)
            . '<div class="fayno-crm__title-row">'
            . '<span class="fayno-crm__title-icon">' . $icon . '</span>'
            . '<div><h1>' . e($title) . '</h1><p>' . e($subtitle) . '</p></div>'
            . '</div></div>'
            . ($actions !== '' ? '<div class="fayno-crm__actions">' . $actions . '</div>' : '')
            . '</header>';
    }
}
