Cópia da sua resposta
{!! $formTitle !!} · enviada em {{ $receivedAt }}

@include('mail.submissions._answers-text')
@include('mail.submissions._files-text')
@if ($editUrl)
Precisa corrigir algo? Você pode editar sua resposta até {{ $editableUntil }}:
{{ $editUrl }}
Não compartilhe este link: quem tiver acesso a ele pode alterar sua resposta.

@endif
--
Você recebeu esta cópia porque respondeu ao formulário "{!! $formTitle !!}". Se não foi você, ignore este email.
