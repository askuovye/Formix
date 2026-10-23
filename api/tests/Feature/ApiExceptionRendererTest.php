<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

it('responde 404 em JSON com mensagem genérica', function () {
    $this->get('/api/v1/rota-inexistente')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
});

it('não vaza o nome do model quando o registro não existe', function () {
    Route::get('/api/v1/_teste/model', fn () => throw (new ModelNotFoundException)->setModel(User::class));

    $this->getJson('/api/v1/_teste/model')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Recurso não encontrado.']);
});

it('responde 405 para método não permitido', function () {
    $this->postJson('/api/v1/health')
        ->assertMethodNotAllowed()
        ->assertExactJson(['message' => 'Método não permitido.']);
});

it('responde 401 para rota autenticada sem sessão', function () {
    Route::middleware('auth:sanctum')->get('/api/v1/_teste/privada', fn () => 'ok');

    $this->getJson('/api/v1/_teste/privada')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Não autenticado.']);
});

it('mantém o formato padrão do Laravel para erros de validação', function () {
    Route::post('/api/v1/_teste/validacao', fn () => throw ValidationException::withMessages([
        'nome' => 'O nome é obrigatório.',
    ]));

    $this->postJson('/api/v1/_teste/validacao')
        ->assertUnprocessable()
        ->assertJsonPath('errors.nome.0', 'O nome é obrigatório.')
        ->assertJsonStructure(['message', 'errors']);
});

it('preserva a mensagem de erros HTTP de domínio', function () {
    Route::get('/api/v1/_teste/conflito', fn () => abort(409, 'Este formulário está fechado.'));

    $this->getJson('/api/v1/_teste/conflito')
        ->assertConflict()
        ->assertExactJson(['message' => 'Este formulário está fechado.']);
});

it('esconde detalhes de erros internos fora do modo debug', function () {
    config(['app.debug' => false]);
    Route::get('/api/v1/_teste/erro', fn () => throw new RuntimeException('detalhe interno sensível'));

    $response = $this->getJson('/api/v1/_teste/erro')
        ->assertInternalServerError()
        ->assertExactJson(['message' => 'Erro interno do servidor.']);

    expect($response->getContent())->not->toContain('detalhe interno sensível');
});

it('responde em JSON mesmo sem o cabeçalho Accept', function () {
    $this->get('/api/v1/rota-inexistente')
        ->assertHeader('Content-Type', 'application/json');
});
