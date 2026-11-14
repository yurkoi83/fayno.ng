<header class="fayno-crm__heading">
    <div class="fayno-crm__heading-main">
        {!! \App\Nova\Support\FaynoBreadcrumbs::render($resourceLabel, $resourceUrl, $currentLabel) !!}
        <div class="fayno-crm__title-row">
            <span class="fayno-crm__title-icon">{!! $icon !!}</span>
            <div>
                <h1>{{ $title }}</h1>
                <p>{{ $subtitle }}</p>
            </div>
        </div>
    </div>
    @if (!empty($actions))
        <div class="fayno-crm__actions">{!! $actions !!}</div>
    @endif
</header>
