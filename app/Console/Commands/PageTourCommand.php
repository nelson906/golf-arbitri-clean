<?php

namespace App\Console\Commands;

use App\Models\Club;
use App\Models\Tournament;
use App\Models\TournamentNotification;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/**
 * Giro di tutte le pagine con i dati del database in uso (2026-10-09).
 *
 * Apre ogni pagina GET come super admin, CRC, ogni admin di zona e i tre
 * arbitri piu' designati, e segnala quelle che vanno in errore (500).
 * Solo lettura: niente POST, mail e chiamate esterne finte. Per MAMP, non
 * per la produzione.
 */
class PageTourCommand extends Command
{
    protected $signature = 'golf:giro-pagine';

    protected $description = 'Apre tutte le pagine per ogni ruolo e segnala quelle che vanno in errore';

    public function handle(Kernel $kernel): int
    {
        if (app()->environment('production')) {
            $this->error('Non in produzione.');

            return self::FAILURE;
        }

        Mail::fake();
        Http::fake(['*' => Http::response([], 200)]);

        $users = array_filter([
            'super admin' => User::where('user_type', 'super_admin')->first(),
            'CRC' => User::where('user_type', 'national_admin')->first(),
        ]);
        foreach (User::where('user_type', 'admin')->whereNotNull('zone_id')->orderBy('zone_id')->get()->unique('zone_id') as $u) {
            $users['SZR '.$u->zone_id] = $u;
        }
        foreach (User::where('user_type', 'referee')->where('is_active', true)->withCount('assignments')
            ->orderByDesc('assignments_count')->take(3)->get() as $i => $u) {
            $users['arbitro '.($i + 1)] = $u;
        }

        $samples = [
            'tournament' => array_filter([
                Tournament::orderBy('start_date')->first(), Tournament::orderByDesc('start_date')->first(),
                Tournament::whereNull('club_id')->first(),
                Tournament::whereHas('tournamentType', fn ($q) => $q->where('is_national', true))->first(),
            ]),
            'user' => array_filter([User::where('user_type', 'referee')->first()]),
            'club' => array_filter([Club::first()]),
            'tournamentNotification' => array_filter([TournamentNotification::first()]),
        ];

        $requests = 0;
        $errors = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || ! in_array('GET', $route->methods(), true)
                || preg_match('#^(_|api/|sanctum|dev/|storage|up$)#', $route->uri())
                || preg_match('/download|export|logout/', $route->uri())) {
                continue;
            }

            $sets = [[]];
            foreach ($route->parameterNames() as $param) {
                if (! isset($samples[$param])) {
                    $sets = [];
                    break;
                }
                $next = [];
                foreach ($sets as $set) {
                    foreach ($samples[$param] as $model) {
                        $next[] = $set + [$param => $model];
                    }
                }
                $sets = $next;
            }

            foreach ($sets as $set) {
                $url = route($name, $set, false);
                foreach ($users as $who => $user) {
                    auth()->login($user);
                    $request = Request::create($url, 'GET');
                    $session = app(\Illuminate\Session\SessionManager::class)->driver();
                    if ($session instanceof \Illuminate\Contracts\Session\Session) {
                        $request->setLaravelSession($session);
                    }
                    $response = $kernel->handle($request);
                    $requests++;
                    if ($response->getStatusCode() >= 500) {
                        $ex = $response->exception ?? null;
                        $errors[] = "{$who}  {$url}  ".($ex ? class_basename($ex).': '.mb_substr($ex->getMessage(), 0, 150) : '');
                    }
                    auth()->logout();
                }
            }
        }

        $this->info("Pagine aperte: {$requests} con ".count($users).' utenti');
        if ($errors === []) {
            $this->info('Nessuna pagina in errore.');

            return self::SUCCESS;
        }
        $this->error('Pagine in errore: '.count($errors));
        foreach ($errors as $e) {
            $this->line('  '.$e);
        }

        return self::FAILURE;
    }
}
