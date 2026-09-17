@php
    $window = \App\Support\PageWindow::make($paginator->currentPage(), $paginator->lastPage());
    $dataPage = $dataPage ?? false;
@endphp
<div class="pager"@if(!empty($pagerId)) id="{{ $pagerId }}"@endif>
    @if($paginator->onFirstPage())
        <span class="page-number disabled">‹</span>
    @else
        <a class="page-number" href="{{ $paginator->previousPageUrl() }}"@if($dataPage) data-page="{{ $paginator->currentPage() - 1 }}"@endif>‹</a>
    @endif
    @if($window['hasStartEllipsis'])
        <span class="pager-ellipsis">...</span>
    @endif
    @foreach($window['pages'] as $page)
        @if($page === $paginator->currentPage())
            <span class="page-number active">{{ $page }}</span>
        @else
            <a class="page-number" href="{{ $paginator->url($page) }}"@if($dataPage) data-page="{{ $page }}"@endif>{{ $page }}</a>
        @endif
    @endforeach
    @if($window['hasEndEllipsis'])
        <span class="pager-ellipsis">...</span>
    @endif
    @if($paginator->hasMorePages())
        <a class="page-number" href="{{ $paginator->nextPageUrl() }}"@if($dataPage) data-page="{{ $paginator->currentPage() + 1 }}"@endif>›</a>
    @else
        <span class="page-number disabled">›</span>
    @endif
</div>
