<?php

namespace App\Http\Controllers;

use App\Services\TransactionBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Native\Desktop\Dialog;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionBackupController extends Controller
{
    public function export(TransactionBackupService $backupService): RedirectResponse|StreamedResponse
    {
        $backupPath = $backupService->createBackup();
        $fileName = 'backup_transaksi_'.now()->format('Ymd_His').'.sqlite';

        if (config('nativephp-internal.running')) {
            $destination = Dialog::new()
                ->title('Simpan backup transaksi')
                ->defaultPath($fileName)
                ->button('Simpan backup')
                ->filter('Database SQLite', ['sqlite', 'db'])
                ->save();

            if (! is_string($destination) || $destination === '') {
                unlink($backupPath);

                return redirect()->route('transactions.index')
                    ->with('info', 'Penyimpanan backup dibatalkan.');
            }

            try {
                if (! copy($backupPath, $destination)) {
                    throw new \RuntimeException('File backup tidak dapat disimpan ke lokasi yang dipilih.');
                }
            } finally {
                if (is_file($backupPath)) {
                    unlink($backupPath);
                }
            }

            return redirect()->route('transactions.index')
                ->with('success', 'Backup SQLite berhasil disimpan.');
        }

        return response()->streamDownload(static function () use ($backupPath): void {
            $stream = fopen($backupPath, 'rb');

            if ($stream === false) {
                unlink($backupPath);

                throw new \RuntimeException('File backup SQLite tidak dapat dibaca.');
            }

            try {
                if (fpassthru($stream) === false) {
                    throw new \RuntimeException('File backup SQLite gagal dikirim.');
                }
            } finally {
                fclose($stream);
                unlink($backupPath);
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.sqlite3',
        ]);
    }

    public function import(Request $request, TransactionBackupService $backupService): RedirectResponse
    {
        $validated = $request->validate([
            'backup' => ['required', 'file', 'max:51200', 'extensions:sqlite,db'],
        ]);

        try {
            $importedCount = $backupService->import($validated['backup']->getRealPath(), $request->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'backup' => $exception->getMessage(),
            ]);
        }

        return redirect()->route('transactions.index')
            ->with('success', "Backup berhasil digabungkan. {$importedCount} transaksi baru ditambahkan.");
    }
}
