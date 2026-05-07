<?php
namespace App\Http\Controllers;

use App\Models\AiLog;
use App\Services\{GeminiService, EventActionService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AudioController extends Controller
{
  public function __construct(
    protected GeminiService $gemini,
    protected EventActionService $actionService
  ) {
  }

  public function process(Request $request)
  {
    $request->validate(['audio' => 'required|file|mimes:mp3,wav,ogg,m4a,webm|max:20480']);
    $aiData = $this->gemini->processAudio(
      base64_encode(file_get_contents($request->file('audio')->getRealPath())),
      $request->file('audio')->getMimeType()
    );

    if (!$aiData || isset($aiData['error'])) {
      return response()->json([
        'error' => $aiData['error'] ?? 'Error de comunicación con la IA'
      ], 422);
    }

    $aiLog = AiLog::create([
      'sync_status' => 'pending_link',
      'client_context' => ['user_id' => $request->user()->id, 'timezone' => $request->user()->timezone],
      'ai_audit' => $aiData,
      'retry_count' => 0
    ]);

    try {
      $res = DB::transaction(fn() => $this->actionService->execute($aiData, $request->user()->id, (string) $aiLog->_id));
      $aiLog->update(['sync_status' => 'linked', 'postgres_event_id' => $res['event']->id, 'linked_at' => now()]);
      return response()->json($res, 200);
    } catch (\Exception $e) {
      $aiLog->increment('retry_count');
      $aiLog->update(['last_retry_at' => now(), 'sync_status' => $aiLog->retry_count >= 3 ? 'orphaned' : 'pending_link']);
      return response()->json(['error' => $e->getMessage()], 400);
    }
  }
}