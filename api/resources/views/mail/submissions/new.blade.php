@extends('mail.layout', ['title' => 'Nova resposta'])

@section('content')
    <h1 style="margin:0 0 4px;font-size:20px;">Nova resposta</h1>
    <p style="margin:0;color:#5b6270;">{{ $formTitle }} · recebida em {{ $receivedAt }}</p>

    @include('mail.submissions._answers')
    @include('mail.submissions._files')

    <p style="margin:16px 0 0;">
        <a href="{{ $dashboardUrl }}" style="display:inline-block;background:#2f5bea;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;">Ver no painel</a>
    </p>
@endsection

@section('footer')
    <p style="margin:0;">Você recebe este email porque está na lista de notificações deste formulário. <a href="{{ $settingsUrl }}" style="color:#5b6270;">Alterar notificações</a>.</p>
    <p style="margin:8px 0 0;">As respostas foram escritas por quem preencheu o formulário. Desconfie de pedidos de senha, pagamento ou links inesperados.</p>
@endsection
