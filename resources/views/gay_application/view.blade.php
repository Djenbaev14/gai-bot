<!-- resources/views/gay_application/view.blade.php -->

{{-- @extends('filament::layouts.app') --}}

{{-- @section('content') --}}
    <div style="text-align:center;">
        <img src="{{ Storage::url($record->document_path) }}" style="max-width:80%; cursor:pointer;" onclick="this.style.width='100%'">
    </div>
{{-- @endsection --}}
