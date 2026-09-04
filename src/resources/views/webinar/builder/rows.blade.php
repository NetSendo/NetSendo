{{-- Renders an ordered list of builder rows. Expects $rows plus the page
     context variables ($webinar, $theme, $ctx) from the parent view. --}}
@foreach($rows as $row)
    @php
        $rowStyle = $row['style'];
        $background = $rowStyle['background'] ?? 'none';
        $template = \App\Services\Webinar\WebinarPageRenderer::gridTemplate($row['layout']);
        $inline = \App\Services\Webinar\WebinarPageRenderer::rowStyle($rowStyle, $theme);
        $marginBottom = \App\Services\Webinar\WebinarPageRenderer::spacing($rowStyle['margin_bottom'] ?? 'md');
    @endphp
    <section class="wb-row wb-row--{{ $background }} wb-align-{{ $rowStyle['align'] }}" style="margin-bottom: {{ $marginBottom }};">
        <div class="wb-row__inner" style="{{ $inline }}; grid-template-columns: {{ $template }};">
            @foreach($row['columns'] as $column)
                <div class="wb-col">
                    @foreach($column['blocks'] as $block)
                        @include('webinar.builder.blocks.' . $block['type'], ['block' => $block, 'props' => $block['props']])
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>
@endforeach
