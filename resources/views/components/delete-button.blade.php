@props(['action', 'confirm' => 'Yakin hapus data ini?', 'label' => 'Hapus'])

<form method="POST" action="{{ $action }}" x-data
      @submit.prevent="$store.confirm.ask({ title: @js($confirm), body: 'Tindakan ini tidak bisa dibatalkan.', confirmLabel: @js($label), tone: 'danger' }).then(ok => ok && $el.submit())">
    @csrf
    @method('DELETE')
    <x-button variant="danger" size="sm" icon="trash">{{ $label }}</x-button>
</form>
