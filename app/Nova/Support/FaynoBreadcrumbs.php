<?php

namespace App\\Nova\\Support;

class FaynoBreadcrumbs
{
    /**
     * Render the shared breadcrumb trail used by Nova resource detail pages.
     */
    public static function render(string $resourceLabel, string $resourceUrl, string $currentLabel): string
    {
        $homeIcon = '<svg class="fayno-breadcrumbs__home-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 10 9-7 9 7"/><path d="M5 9v11h14V9M9 20v-6h6v6"/></svg>';

        return '<nav class="fayno-crm__breadcrumbs" aria-label="Хлібні крихти">'
            . '<a class="fayno-crm__breadcrumb-home" href="' . e(url('/nova')) . '" aria-label="Головна">' . $homeIcon . '</a>'
            . '<span class="fayno-crm__breadcrumb-separator" aria-hidden="true">›</span>'
            . '<a href="' . e($resourceUrl) . '">' . e($resourceLabel) . '</a>'
            . '<span class="fayno-crm__breadcrumb-separator" aria-hidden="true">›</span>'
            . '<span class="fayno-crm__breadcrumb-current" aria-current="page">' . e($currentLabel) . '</span>'
            . '</nav>';
    }
}
