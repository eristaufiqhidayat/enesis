@props(['id'])

{{-- Dikendalikan lewat JS: toggle class "hidden" dan atribut [data-modal-dismiss="{id}"] pada tombol penutup. --}}
<div {{ $attributes->merge(['class' => 'modal-container hidden', 'id' => $id]) }}>
    {{ $slot }}
</div>
