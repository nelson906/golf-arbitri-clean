<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// NOTA EMAIL (audit 2026-06): i Mailable implementano ShouldQueue + afterCommit,
// ma con QUEUE_CONNECTION=sync (scelta deliberata: pochi invii, niente cron/worker)
// l'invio resta immediato e sincrono. afterCommit è comunque rispettato:
// dentro una transazione DB l'email parte solo al commit, scartata al rollback.
// Se in futuro i volumi crescono: QUEUE_CONNECTION=database + worker/cron.
