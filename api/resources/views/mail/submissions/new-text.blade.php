Nova resposta
{!! $formTitle !!} · recebida em {{ $receivedAt }}

@include('mail.submissions._answers-text')
@include('mail.submissions._files-text')
Ver no painel: {{ $dashboardUrl }}

--
Você recebe este email porque está na lista de notificações deste formulário.
Alterar notificações: {{ $settingsUrl }}
As respostas foram escritas por quem preencheu o formulário. Desconfie de pedidos de senha, pagamento ou links inesperados.
