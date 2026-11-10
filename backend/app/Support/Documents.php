<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Acceso al almacenamiento privado de facturas y certificados.
 */
class Documents
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.documents_disk', 'documentos'));
    }

    /**
     * Devuelve el disco donde está el archivo. Los PDF emitidos antes de pasar
     * al disco privado pueden seguir en el disco public hasta que se migren.
     */
    public static function locate(?string $path): ?Filesystem
    {
        if (!$path) {
            return null;
        }

        if (self::disk()->exists($path)) {
            return self::disk();
        }

        return Storage::disk('public')->exists($path) ? Storage::disk('public') : null;
    }

    public static function exists(?string $path): bool
    {
        return self::locate($path) !== null;
    }
}
