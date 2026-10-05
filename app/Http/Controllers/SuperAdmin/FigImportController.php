<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Console\Commands\ImportFedergolfCommittees;
use App\Http\Controllers\Controller;
use App\Support\FigImportAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Caricamento completo dei Comitati di Gara da federgolf.it, dalla pagina web.
 *
 * Decisione 2026-10-05: su Aruba `php artisan` non si puo' lanciare, quindi
 * il comando federgolf:import-committees si avvia da qui, riservato a un
 * solo account (FigImportAccess). Una pagina web su hosting condiviso viene
 * interrotta dopo poche decine di secondi: il comando gira a blocchi di
 * poche gare per richiesta, e la pagina chiama i blocchi uno dopo l'altro.
 */
class FigImportController extends Controller
{
    /** Gare per richiesta: ~2-3 secondi a gara verso federgolf.it. */
    public const BLOCCO = 5;

    private function authorizeAccess(): void
    {
        $user = auth()->user();

        if (! FigImportAccess::allows($user instanceof \App\Models\User ? $user : null)) {
            abort(403);
        }
    }

    public function index(): View
    {
        $this->authorizeAccess();

        $anni = [(int) date('Y'), (int) date('Y') - 1];

        return view('super-admin.fig-import.index', [
            'anni' => $anni,
            'blocco' => self::BLOCCO,
        ]);
    }

    /**
     * Elabora un blocco di gare. Risponde con i contatori del blocco, il
     * registro testuale e la posizione del blocco successivo.
     */
    public function block(Request $request): JsonResponse
    {
        $this->authorizeAccess();

        $request->validate([
            'anno' => 'required|integer|min:2000|max:2100',
            'offset' => 'required|integer|min:0',
            'replace' => 'boolean',
            'dry_run' => 'boolean',
            'run' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{8,64}$/'],
        ]);

        @set_time_limit(120);

        $offset = $request->integer('offset');

        $command = app(ImportFedergolfCommittees::class);
        $command->setLaravel(app());

        $input = new ArrayInput([
            '--anno' => (string) $request->integer('anno'),
            '--offset' => (string) $offset,
            '--limit' => (string) self::BLOCCO,
            '--run' => $request->string('run')->toString(),
            '--replace' => $request->boolean('replace'),
            '--dry-run' => $request->boolean('dry_run'),
        ]);
        $output = new BufferedOutput;

        try {
            $exitCode = $command->run($input, $output);
        } catch (\Throwable $e) {
            Log::error('FigImportController::block', ['offset' => $offset, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Errore durante il blocco: '.$e->getMessage(),
                'log' => $output->fetch(),
            ], 500);
        }

        $stats = $command->stats();
        $nextOffset = $offset + self::BLOCCO;

        return response()->json([
            'success' => $exitCode === 0,
            'message' => $exitCode === 0 ? null : 'Nessuna gara trovata su federgolf.it per questo anno.',
            'stats' => $stats,
            'log' => $output->fetch(),
            'next_offset' => $nextOffset,
            'done' => $exitCode !== 0 || $nextOffset >= $stats['gare_disponibili'],
        ]);
    }
}
