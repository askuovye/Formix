@extends('mail.layout', ['title' => 'Cópia da sua resposta'])

@section('content')
    <h1 style="margin:0 0 4px;font-size:20px;">Cópia da sua resposta</h1>
    <p style="margin:0;color:#5b6270;">{{ $formTitle }} · enviada em {{ $receivedAt }}</p>

    @include('mail.submissions._answers')
    @include('mail.submissions._files')

    @if ($editUrl)
        <p style="margin:16px 0 0;">Precisa corrigir algo? Você pode editar sua resposta até {{ $editableUntil }}.</p>
        <p style="margin:12px 0 0;">
            <a href="{{ $editUrl }}" style="display:inline-block;background:#2f5bea;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;">Editar minha resposta</a>
        </p>
        <p style="margin:12px 0 0;font-size:13px;color:#5b6270;">Não compartilhe este link: quem tiver acesso a ele pode alterar sua resposta.</p>
    @endif
@endsection

@section('footer')
    <p style="margin:0;">Você recebeu esta cópia porque respondeu ao formulário "{{ $formTitle }}". Se não foi você, ignore este email.</p>
@endsection
