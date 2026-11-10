<?php

namespace App\Commands;

use App\Models\Appointment;
use App\Models\Certificate;
use App\Support\Documents;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MoveDocumentsToPrivateDisk extends Command
{
    protected $signature = 'documentos:migrar';
    protected $description = 'Mueve las facturas y certificados emitidos antes al disco privado de documentos';

    public function handle(): int
    {
        $paths = Certificate::query()->pluck('invoice_url')
            ->merge(Certificate::query()->pluck('certificate_url'))
            ->merge(Appointment::query()->whereNotNull('certificado_url')->pluck('certificado_url'))
            ->filter()
            ->unique();

        $public = Storage::disk('public');
        $movidos = 0;

        foreach ($paths as $path) {
            if (!$public->exists($path) || Documents::disk()->exists($path)) {
                continue;
            }

            Documents::disk()->put($path, $public->get($path));
            $public->delete($path);
            $movidos++;
        }

        $this->info("Archivos movidos al disco privado: {$movidos}");
        return Command::SUCCESS;
    }
}
