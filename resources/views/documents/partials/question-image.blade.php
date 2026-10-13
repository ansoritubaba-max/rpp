@php
    $library = app(\App\Services\Documents\QuestionAssetLibrary::class);
    $assetKey = data_get($image ?? [], 'asset');
    $count = max(1, min(10, (int) data_get($image ?? [], 'count', 1)));
    $source = ($pdf ?? false) ? $library->dataUri($assetKey) : $library->url($assetKey);
@endphp
@if($source)
    <div class="question-images" aria-label="{{ $count }} gambar {{ $library->label($assetKey) }}" style="margin: 8px 0;">
        @for($index = 0; $index < $count; $index++)
            <img src="{{ $source }}" alt="{{ $library->label($assetKey) }}" width="68" height="68" style="display: inline-block; width: 68px; height: 68px; margin: 3px; vertical-align: middle;">
        @endfor
    </div>
@endif
