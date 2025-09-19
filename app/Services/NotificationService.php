<?php

namespace App\Services;

use App\Services\DocumentGenerationService;

/**
 * 🧹 NOTIFICATION SERVICE - COMPLETAMENTE PULITO
 *
 * Tutti i metodi sono stati spostati in NotificationController
 * Questo service può essere completamente ELIMINATO o mantenuto
 * solo per future espansioni del sistema di notifiche.
 */
class NotificationService
{
    protected $documentService;

    public function __construct(DocumentGenerationService $documentService)
    {
        $this->documentService = $documentService;
    }

    // 🚫 TUTTI I METODI ELIMINATI - FUNZIONALITA' IN NotificationController

    /**
     * Placeholder per future funzionalità di notifica avanzate
     * se necessarie (batch processing, queue management, etc.)
     */
}
