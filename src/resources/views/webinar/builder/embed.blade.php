{{-- Builder blocks embedded inside a page that keeps its own chrome (waiting
     room, replay). The theme is scoped to .wb-scope so it never restyles the
     host page. Expects an optional $builderPage = ['theme'=>…, 'rows'=>…, 'ctx'=>…]. --}}
@php
    $builderPage = $builderPage ?? null;
    $builderRows = $builderPage['rows'] ?? [];
    $theme = $builderPage['theme'] ?? [];
    $ctx = $builderPage['ctx'] ?? [];
@endphp
@if(!empty($builderRows))
    <div class="wb-scope">
        <style>{!! \App\Services\Webinar\WebinarPageRenderer::css($theme, true) !!}</style>
        <div class="wb-page">
            @include('webinar.builder.rows', ['rows' => $builderRows])
        </div>
    </div>
@endif
