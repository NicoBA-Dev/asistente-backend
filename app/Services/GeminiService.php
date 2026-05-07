<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class GeminiService
{
  protected string $apiKey;
  protected string $baseUrl;

  public function __construct()
  {
    $this->apiKey = Config::get('services.gemini.api_key');
    $this->baseUrl = Config::get('services.gemini.url');
  }

  public function processAudio(string $audioBase64, string $mimeType = 'audio/mp3')
  {
    // Forzamos el tipo MIME para evitar el bug de "0 frames"
    if (strpos($mimeType, 'video/') !== false || $mimeType === 'application/octet-stream') {
      $mimeType = 'audio/webm';
    }

    $hoy = now()->setTimezone('America/La_Paz')->toIso8601String();

    $prompt = "Eres un experto asistente logístico. Hoy es: {$hoy}.
    Analiza el audio y determina la INTENCIÓN real del usuario.

    REGLAS DE ORO:
    1. Si el usuario dice 'modifica', 'cambia', 'mueve' o 'reprograma', la intención es MODIFICAR.
    2. Si el usuario dice 'borra', 'cancela' o 'quita', la intención es ELIMINAR.
    3. Para MODIFICAR: 
       - 'palabras_clave': La palabra clave del evento que ya existe (ej: 'doctora').
       - 'nuevos_datos': Solo lo que cambia (ej: si dice 'para las 7:30', pones esa hora).
    4. NUNCA uses la zona horaria 'Z'. Usa formato Y-m-d H:i:s.

    ESTRUCTURA JSON A SEGUIR:
    {
      \"intencion\": \"CREAR o MODIFICAR o ELIMINAR\",
      \"parametros\": {
        \"titulo\": \"Título descriptivo\",
        \"categoria\": \"Trabajo, Personal, etc.\",
        \"fecha_evento\": \"Fecha detectada o null\",
        \"duracion_minutos\": 60,
        \"recordatorio_minutos\": 1,
        \"contactos\": [],
        \"fecha_referencia\": \"Fecha original si la menciona, sino null\",
        \"palabras_clave\": \"Palabra para buscar el evento existente\",
        \"nuevos_datos\": {
           \"titulo\": \"Nuevo título si aplica\",
           \"fecha_evento\": \"Nueva fecha si aplica\"
        }
      }
    }";

    $response = Http::withoutVerifying()->post("{$this->baseUrl}?key={$this->apiKey}", [
      'contents' => [
        [
          'parts' => [
            ['text' => $prompt],
            [
              'inline_data' => [
                'mime_type' => $mimeType,
                'data' => $audioBase64
              ]
            ]
          ]
        ]
      ]
    ]);

    Log::info("=== DIAGNÓSTICO GEMINI ===");
    Log::info("MIME TYPE CORREGIDO: " . $mimeType);
    Log::info("STATUS HTTP: " . $response->status());
    Log::info("RESPUESTA COMPLETA DE GOOGLE: " . $response->body());
    Log::info("==========================");

    $data = $response->json();

    // Si Google devuelve un error (ej: cuota excedida)
    if (isset($data['error'])) {
      Log::error("GEMINI API ERROR: " . ($data['error']['message'] ?? 'Unknown'));
      return ['error' => $data['error']['message'] ?? 'API Error'];
    }

    // 2. Validar integridad de la respuesta (Regla 20)
    $candidate = $data['candidates'][0] ?? null;
    $finishReason = $candidate['finishReason'] ?? 'UNKNOWN';

    if ($finishReason !== 'STOP') {
      $errorDetails = [
        'MAX_TOKENS' => 'La respuesta fue demasiado larga y quedó incompleta.',
        'SAFETY' => 'El contenido fue bloqueado por filtros de seguridad.',
        'RECITATION' => 'Contenido bloqueado por derechos de autor.',
        'OTHER' => 'La IA se detuvo por un motivo inesperado.'
      ];

      $msg = $errorDetails[$finishReason] ?? "La IA no pudo terminar la respuesta (Motivo: $finishReason)";
      Log::warning("GEMINI FINISH REASON NO EXITOSO: $finishReason");

      return ['error' => $msg];
    }

    $rawText = $candidate['content']['parts'][0]['text'] ?? '';

    if (empty($rawText)) {
      return ['error' => 'La IA no devolvió texto. Revisa el audio.'];
    }
    preg_match('/\{.*\}/s', $rawText, $matches);
    $cleanJson = $matches[0] ?? '{}';

    return json_decode($cleanJson, true);
  }
}