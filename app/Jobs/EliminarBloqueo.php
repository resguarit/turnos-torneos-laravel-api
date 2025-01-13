<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\BloqueoTemporal;

class EliminarBloqueo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bloqueoId;

    public function __construct($bloqueoId)
    {
        $this->bloqueoId = $bloqueoId;
    }

    public function handle()
    {
        BloqueoTemporal::find($this->bloqueoId)->delete();
    }
}
