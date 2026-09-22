@php
    $inputId = $inputId ?? 'campaign_combo';
    $inputName = $inputName ?? 'campaign';
    $menuId = $menuId ?? $inputId.'_menu';
    $required = $required ?? true;
    $campaigns = $campaigns ?? collect();
    $placeholder = $placeholder ?? 'Select or type a campaign...';
    $emptyText = $emptyText ?? 'No campaigns yet. Type a new name.';
@endphp
<div class="pin-campaign-combo" data-campaign-combo>
    <input class="form-control" name="{{ $inputName }}" id="{{ $inputId }}" placeholder="{{ $placeholder }}" autocomplete="off" @if($required) required @endif aria-autocomplete="list" aria-controls="{{ $menuId }}">
    <div class="pin-campaign-menu" id="{{ $menuId }}" hidden role="listbox">
        @forelse($campaigns as $campaign)
            <button class="pin-campaign-option" type="button" role="option" data-name="{{ $campaign->name }}" data-id="{{ $campaign->id }}" data-fte="{{ $campaign->fte }}">{{ $campaign->name }}</button>
        @empty
            <div class="pin-campaign-empty">{{ $emptyText }}</div>
        @endforelse
    </div>
</div>
