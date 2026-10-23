@extends('mail.layout', ['title' => 'Resposta atualizada'])

@section('content')
    <h1 style="margin:0 0 4px;font-size:20px;">Resposta atualizada</h1>
    <p style="margin:0;color:#5b6270;">{{ $formTitle }} · alterada pelo respondente em {{ $editedAt }}</p>

    <p style="margin:16px 0 0;">Como ficou a resposta:</p>
    @include('mail.submissions._answers')
    @include('mail.submissions._files')

    <p style="margin:16px 0 0;">
        <a href="{{ $dashboardUrl }}" style="display:inline-block;background:#2f5bea;color:#ffffff;text-decoration:none;padding:10px 16px;border-radius:6px;">Ver no painel</a>
    </p>
    <p style="margin:12px 0 0;font-size:13px;color:#5b6270;">A versão anterior fica guardada no histórico da resposta.</p>
@endsection

@section('footer')
    <p style="margin:0;">Você recebe este email porque está na lista de notificações deste formulário. <a href="{{ $settingsUrl }}" style="color:#5b6270;">Alterar notificações</a>.</p>
@endsection
