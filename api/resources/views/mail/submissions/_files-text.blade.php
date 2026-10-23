{{-- Texto puro: sem escape de entidades (ver _answers-text). --}}
@if ($fileGroups !== [])
Anexos
@foreach ($fileGroups as $group)
{!! $group['label'] !!}:
@foreach ($group['files'] as $file)
- {!! $file['name'] !!} ({{ $file['size'] }}){!! $file['url'] ? ': '.$file['url'] : '' !!}
@endforeach

@endforeach
@if ($fileGroups[0]['files'][0]['url'] ?? null)
Os links de download valem por {{ config('submissions.attachment_link_ttl_days') }} dias. Os arquivos não passam por antivírus: abra com cautela.

@endif
@endif
