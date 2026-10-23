{{--
    Versão texto puro: o conteúdo é exibido como texto pelo cliente de email, nunca
    interpretado como HTML, então sai sem escape de entidades (senão "&" viraria "&amp;").
    Caracteres de controle já foram removidos na normalização das respostas.
--}}
@foreach ($rows as $row)
{!! $row['label'] !!}:
{!! $row['value'] !!}

@endforeach
