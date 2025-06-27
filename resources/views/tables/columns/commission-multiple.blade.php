@include('partials.head')

@php
    $record = $getRecord();
@endphp
<div class="text-xs">
    <div class="flex flex-col justify-start">
        <span class="font-medium text-gray-900 dark:text-gray-100">(%)US$: {{ $record['commission_agency_master_usd'] }}</span>

        <span class="font-medium text-gray-900 dark:text-gray-100">(%)VES: {{ $record['commission_agency_master_ves'] }}</span>
    </div>
    {{-- {{ $getState() }} --}}
</div>
