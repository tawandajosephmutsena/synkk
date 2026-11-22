<x-mail::message>
# {{ $headline }}

**Vault:** {{ $vault->name }} · **Schedule:** {{ ucfirst($period) }} Digest · **Activity:** {{ $changesCount }} changes across {{ $filesTouched }} files

{{ $summary }}

---

{!! \Illuminate\Support\Str::markdown($markdownBody, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}

<x-mail::button :url="$vaultUrl">
Open Vault in Synkk
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
