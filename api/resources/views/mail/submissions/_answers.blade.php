{{-- Respostas do formulário. Valores vêm do respondente: sempre escapados; nl2br só depois do e(). --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;">
    @foreach ($rows as $row)
        <tr>
            <td style="padding:10px 0;border-bottom:1px solid #eceef1;">
                <p style="margin:0 0 4px;font-size:13px;color:#5b6270;font-weight:bold;">{{ $row['label'] }}</p>
                <p style="margin:0;font-size:15px;{{ $row['answered'] ? '' : 'color:#8a909c;font-style:italic;' }}">
                    @if ($row['multiline'])
                        {!! nl2br(e($row['value'])) !!}
                    @else
                        {{ $row['value'] }}
                    @endif
                </p>
            </td>
        </tr>
    @endforeach
</table>
