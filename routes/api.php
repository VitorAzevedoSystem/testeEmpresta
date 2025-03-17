<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

Route::post('/simulacao', function (Request $request) {
    // Validação dos dados recebidos
    $request->validate([
        'valor_emprestimo' => 'required|numeric|min:1',
        'instituicoes' => 'array|nullable',
        'convenios' => 'array|nullable',
        'parcela' => 'numeric|nullable',
    ]);

    // Carregar os dados do JSON
    $path = "json/taxas_instituicoes.json";
    if (!Storage::exists($path)) {
        return response()->json(['error' => 'Arquivo de dados não encontrado'], 404);
    }
    $jsonContent = Storage::get($path);
    $dados = json_decode($jsonContent, true);

    // Log dos dados carregados
    Log::info('Dados carregados do JSON:', $dados);
    Log::info('Parâmetros recebidos:', $request->all());

    // Filtros opcionais (instituição, convênio, parcelas)
    $instituicoes = $request->input('instituicoes', []);
    $convenios = $request->input('convenios', []);
    $parcela = $request->input('parcela');

    $valor_emprestimo = $request->input('valor_emprestimo');

    // Filtrando os dados de acordo com os parâmetros opcionais
    $dadosFiltrados = array_filter($dados, function ($item) use ($instituicoes, $convenios, $parcela) {
        if (!is_array($item)) {
            return false;
        }

        if (!isset($item['instituicao']) || !isset($item['convenio']) || !isset($item['parcelas'])) {
            return false;
        }

        if (!empty($instituicoes) && !in_array($item['instituicao'], $instituicoes)) {
            return false;
        }
        if (!empty($convenios) && !in_array($item['convenio'], $convenios)) {
            return false;
        }
        if (!empty($parcela) && $item['parcelas'] != $parcela) {
            return false;
        }
        return true;
    });

    Log::info('Dados filtrados:', $dadosFiltrados);

    // Montando a resposta com cálculo das parcelas
    $resultado = [];

    foreach ($dadosFiltrados as $item) {
        $valor_parcela = $valor_emprestimo * $item['coeficiente'];

        $resultado[$item['instituicao']][] = [
            'parcelas' => $item['parcelas'],
            'valor_parcela' => round($valor_parcela, 2),
            'taxa_juros' => $item['taxaJuros'],
            'convenio' => $item['convenio']
        ];
    }

    // Se nenhum dado for encontrado, retornar mensagem vazia
    if (empty($resultado)) {
        return response()->json(['message' => 'Nenhuma simulação disponível com os filtros aplicados'], 200);
    }

    return response()->json($resultado, 200);
});
