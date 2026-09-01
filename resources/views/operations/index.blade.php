@extends('layouts.app')
@section('content')
<div class="page-head">
    <div>
        <h1 class="page-title">{{ $config['title'] }}</h1>
        <p class="page-subtitle">{{ $config['description'] }}</p>
    </div>
    <div class="toolbar">
        <form class="search-box media-search-form" id="operationSearchForm" method="GET" action="{{ route($module) }}">
            <span class="search-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"></circle><path d="m20 20-3.5-3.5"></path></svg>
            </span>
            <input id="operationSearchInput" name="search" value="{{ $search }}" placeholder="Search {{ $config['title'] }}" aria-label="Search {{ $config['title'] }}" autocomplete="off">
            <button type="button" class="search-clear" data-clear-search aria-label="Clear search" title="Clear search">×</button>
            @if(request('status'))<input type="hidden" name="status" value="{{ request('status') }}">@endif
            @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
        </form>
        @if(count($statusOptions))
            <form method="GET" action="{{ route($module) }}">
                @if($search !== '')<input type="hidden" name="search" value="{{ $search }}">@endif
                @if(request('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                <select class="select" name="status" onchange="this.form.submit()" aria-label="Filter by status">
                    <option value="">All Statuses</option>
                    @foreach($statusOptions as $opt)
                        <option value="{{ $opt }}" {{ request('status')===$opt?'selected':'' }}>{{ $opt }}</option>
                    @endforeach
                </select>
            </form>
        @endif
        <button class="btn primary" type="submit" form="operationSearchForm">Search</button>
        @if($search !== '' || request('status'))
            <a class="btn secondary" href="{{ route($module) }}">Reset</a>
        @endif
        @if(auth()->user()->hasPermission('media.export'))
        @include('partials.data-transfer', [
            'canExport' => true,
            'canImport' => auth()->user()->hasPermission('media.create'),
            'exportUrl' => route($module.'.export', request()->query()),
            'templateUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.template') : '',
            'previewUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.preview') : '',
            'confirmUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.confirm') : '',
            'errorsUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.errors') : '',
            'previewHeaders' => array_values($config['columns']),
            'entityTitle' => $config['title'],
        ])
        @endif
        @if(auth()->user()->hasPermission('media.create'))
            <button class="plus-btn" type="button" id="operationAddButton" aria-label="Add {{ $config['title'] }}" title="Add {{ $config['title'] }}">+</button>
        @endif
    </div>
</div>

<div class="table-card table-wrap">
<table aria-label="{{ $config['title'] }}">
@php $numericColumns = ['monthly_cost', 'retention_days', 'port_number', 'sim_number', 'number', 'asset_code']; @endphp
<thead>
<tr>
    <th>Id</th>
    @foreach($config['columns'] as $field => $label)
        <th @class(['num-col' => in_array($field, $numericColumns, true)])>{{ $label }}</th>
    @endforeach
    <th>Last Updated</th>
    <th class="actions-column">Actions</th>
</tr>
</thead>
<tbody>
@forelse($records as $record)
<tr>
    <td>{{ ($records->firstItem() ?? 1) + $loop->index }}</td>
    @foreach($config['columns'] as $field=>$label)
        @php $isNumericCol = in_array($field, $numericColumns, true); @endphp
        <td @class(['num-col' => $isNumericCol])>
            @if($field==='monthly_cost')
                <span class="num-align" data-label="{{ $label }}">₱{{ number_format((float) $record->$field, 2) }}</span>
            @elseif($field==='status')
                <span class="status-pill {{ in_array($record->$field, ['Active', 'Available']) ? 'online' : (in_array($record->$field, ['In Use', 'Expiring']) ? 'unknown' : 'offline') }}">{{ $record->$field }}</span>
            @elseif($isNumericCol)
                <span class="num-align" data-label="{{ $label }}">{{ $record->$field }}</span>
            @else
                {{ $record->$field }}
            @endif
        </td>
    @endforeach
    @php
        $recordId = (int) $record->getKey();
        $editValues = [];
        foreach ($config['fields'] as $field) {
            $value = $record->{$field};
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }
            $editValues[$field] = $value;
        }
    @endphp
    <td>{{ $record->updated_at?->format('M d, Y h:i A') ?? '—' }}</td>
    <td class="actions-column">
        <div class="row-actions">
            @if(auth()->user()->hasPermission('media.edit'))
                <button class="action-btn edit" type="button" data-operation-edit data-id="{{ $recordId }}" data-values='@json($editValues)' title="Edit {{ $config['title'] }}" aria-label="Edit {{ $config['title'] }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path></svg>
                </button>
            @endif
            @if(auth()->user()->hasPermission('media.delete'))
                <form method="POST" action="{{ route($module.'.destroy', ['id' => $recordId]) }}" data-confirm="Delete this record?" data-confirm-title="Delete Record" data-confirm-ok="Delete">
                    @csrf
                    @method('DELETE')
                    <button class="action-btn delete" type="submit" title="Delete {{ $config['title'] }}" aria-label="Delete {{ $config['title'] }}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="m6 7 1 14h10l1-14"></path><path d="M9 7V4h6v3"></path></svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
@empty
<tr><td colspan="{{ count($config['columns']) + 3 }}"><div class="empty-state">No {{ $config['title'] }} records found.</div></td></tr>
@endforelse
</tbody>
</table>
<div class="table-footer">
    <span>Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} entries</span>
    <div class="footer-right">
        <span>Records per page:</span>
        <select class="per-page-select" onchange="location.href=this.value" aria-label="Records per page">
            @foreach([5,10,25,50] as $size)
                <option value="{{ request()->fullUrlWithQuery(['per_page'=>$size,'page'=>1]) }}" {{ $perPage===$size?'selected':'' }}>{{ $size }}</option>
            @endforeach
        </select>
        <div class="pager">
            @for($page=1;$page<=$records->lastPage();$page++)
                @if($page===$records->currentPage())
                    <span class="page-number active">{{ $page }}</span>
                @else
                    <a class="page-number" href="{{ $records->url($page) }}">{{ $page }}</a>
                @endif
            @endfor
        </div>
    </div>
</div>
</div>
@endsection

@push('modals')
@if(auth()->user()->hasPermission('media.create') || auth()->user()->hasPermission('media.edit'))
<div class="modal-backdrop" id="moduleModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="moduleModalTitle">Add {{ $config['title'] }}</h3>
            <button type="button" class="close-btn" data-close="moduleModal">×</button>
        </div>
        <form id="moduleForm" method="POST" action="{{ route($module.'.store') }}">
            @csrf
            <input type="hidden" name="_method" id="moduleMethod" value="POST">
            <div class="modal-body">
                <div class="form-grid">
                    @foreach($config['fields'] as $field)
                    <div class="form-group {{ $field==='description' ? 'full' : '' }}">
                        <label for="field_{{ $field }}">{{ ucwords(str_replace('_', ' ', $field)) }}</label>
                        @if($field==='status')
                            <select class="form-control" name="{{ $field }}" id="field_{{ $field }}">
                                @foreach($statusOptions as $opt)
                                    <option value="{{ $opt }}">{{ $opt }}</option>
                                @endforeach
                            </select>
                        @elseif($field==='gateway')
                            <select class="form-control" name="{{ $field }}" id="field_{{ $field }}">
                                <option value="">Select Media Gateway</option>
                                @foreach($gateways as $gateway)
                                    <option value="{{ $gateway->site_code }}">{{ $gateway->site_code }} — {{ $gateway->site_name }}</option>
                                @endforeach
                            </select>
                        @elseif($field==='description')
                            <textarea class="form-control" name="{{ $field }}" id="field_{{ $field }}"></textarea>
                        @elseif(in_array($field, ['contract_start','contract_end','reported_on']))
                            <input class="form-control" type="date" name="{{ $field }}" id="field_{{ $field }}">
                        @elseif(in_array($field, ['monthly_cost','retention_days']))
                            <input class="form-control" type="number" step="0.01" min="0" name="{{ $field }}" id="field_{{ $field }}">
                        @else
                            <input class="form-control" name="{{ $field }}" id="field_{{ $field }}" {{ in_array($field, ['description','channel','gateway','peer','context','codec','imsi','assigned_to','location','role','program','assigned_channel','issue','reported_on','retention_days']) ? '' : 'required' }}>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn secondary" data-close="moduleModal">Cancel</button>
                <button class="btn primary" type="submit">Save {{ $config['title'] }}</button>
            </div>
        </form>
    </div>
</div>
@endif

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('moduleModal');
    const form = document.getElementById('moduleForm');
    const method = document.getElementById('moduleMethod');
    const storeAction = @json(route($module.'.store'));
    function resetAdd() {
        if (!form || !method) return;
        method.value = 'POST';
        form.action = storeAction;
        document.getElementById('moduleModalTitle').textContent = @json('Add '.$config['title']);
        form.reset();
    }
    document.getElementById('operationAddButton')?.addEventListener('click', () => {
        resetAdd();
        modal?.classList.add('visible');
    });
    document.querySelectorAll('[data-operation-edit]').forEach((button) => button.addEventListener('click', () => {
        const recordId = Number(button.dataset.id);
        if (!Number.isInteger(recordId) || recordId < 1) return;
        const values = JSON.parse(button.dataset.values || '{}');
        document.getElementById('moduleModalTitle').textContent = @json('Edit '.$config['title']);
        method.value = 'PUT';
        form.action = @json(url('/'.$module)) + '/' + recordId;
        Object.keys(values).forEach((key) => {
            const field = document.getElementById('field_' + key);
            if (field) field.value = values[key] ?? '';
        });
        modal?.classList.add('visible');
    }));
});
</script>
@include('partials.inventory-import-script', [
    'previewUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.preview') : '',
    'confirmUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.confirm') : '',
    'errorsUrl' => auth()->user()->hasPermission('media.create') ? route($module.'.import.errors') : '',
    'previewFields' => array_keys($config['columns']),
])
@endpush
