<?php

// app/Helpers/helpers.php
//
// Funzioni helper globali (namespace radice).
// DEVONO stare qui e NON in RefereeLevelsHelper.php perché quel file usa
// `namespace App\Helpers;` — le funzioni definite lì sarebbero accessibili
// solo come \App\Helpers\referee_levels(), non come referee_levels().
//
// Questo file è caricato via Composer "files" autoload (composer.json),
// garantendo che le funzioni siano disponibili globalmente in tutta l'app.

use App\Helpers\RefereeLevelsHelper;

if (! function_exists('referee_levels')) {
    /**
     * Ritorna i livelli arbitro per select HTML.
     * Alias globale di RefereeLevelsHelper::getSelectOptions().
     */
    function referee_levels(bool $includeArchived = false): array
    {
        return RefereeLevelsHelper::getSelectOptions($includeArchived);
    }
}

if (! function_exists('normalize_referee_level')) {
    /**
     * Normalizza una variante di livello al valore enum DB.
     * Alias globale di RefereeLevelsHelper::normalize().
     */
    function normalize_referee_level(?string $level): ?string
    {
        return RefereeLevelsHelper::normalize($level);
    }
}

if (! function_exists('referee_level_label')) {
    /**
     * Ritorna la label leggibile per un livello arbitro.
     * Alias globale di RefereeLevelsHelper::getLabel().
     */
    function referee_level_label(?string $level): string
    {
        return RefereeLevelsHelper::getLabel($level);
    }
}
