<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\{AiLog, Event};
use App\Services\EventActionService;
use Illuminate\Support\Facades\DB;

class ReconcileAiLogs extends Command
{
  protected $signature = 'sync:reconcile';
  protected $description = 'Reprocesa logs de IA huérfanos';

  public function __construct(protected EventActionService $actionService)
  {
    parent::__construct();
  }

  public function handle()
  {
    $orphans = AiLog::whereIn('sync_status', ['pending_link', 'orphaned'])
      ->where('retry_count', '<', 3)->get();

    /** @var \App\Models\AiLog $log */
    foreach ($orphans as $log) {
      if (Event::where('mongo_log_id', (string) $log->_id)->exists()) {
        $log->update(['sync_status' => 'linked']);
        continue;
      }

      try {
        $userId = $log->client_context['user_id'];
        $res = DB::transaction(fn() => $this->actionService->execute($log->ai_audit, $userId, (string) $log->_id));
        $log->update(['sync_status' => 'linked', 'postgres_event_id' => $res['event']->id, 'linked_at' => now()]);
        $this->info("Log {$log->_id} sincronizado con éxito.");
      } catch (\Exception $e) {
        $log->increment('retry_count');
        $this->error("Fallo al sincronizar log {$log->_id}: " . $e->getMessage());
      }
    }
  }
}