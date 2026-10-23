{{-- Anexos por campo. Nome do arquivo vem do respondente: sempre escapado. Link só no email ao dono. --}}
@if ($fileGroups !== [])
    <h2 style="margin:24px 0 8px;font-size:16px;">Anexos</h2>
    @foreach ($fileGroups as $group)
        <p style="margin:12px 0 4px;font-size:13px;color:#5b6270;font-weight:bold;">{{ $group['label'] }}</p>
        <ul style="margin:0;padding-left:20px;">
            @foreach ($group['files'] as $file)
                <li style="margin:2px 0;">
                    @if ($file['url'])
                        <a href="{{ $file['url'] }}" style="color:#2f5bea;">{{ $file['name'] }}</a>
                    @else
                        {{ $file['name'] }}
                    @endif
                    <span style="color:#8a909c;">({{ $file['size'] }})</span>
                </li>
            @endforeach
        </ul>
    @endforeach
    @if ($fileGroups[0]['files'][0]['url'] ?? null)
        <p style="margin:12px 0 0;font-size:12px;color:#5b6270;">Os links de download valem por {{ config('submissions.attachment_link_ttl_days') }} dias. Os arquivos não passam por antivírus: abra com cautela.</p>
    @endif
@endif
