<?php

namespace Tests\Feature;

use App\Support\OrphanFinder;
use Tests\TestCase;

/**
 * Codice orfano (2026-10-09): rotte senza link, viste mai aperte, azioni dei
 * controller senza rotta. Un orfano NUOVO fa diventare il test rosso: o lo si
 * collega, o lo si toglie, o (se e' voluto) lo si aggiunge qui sotto con il
 * motivo. Anche una voce di questa lista che non e' piu' orfana fa fallire il
 * test, cosi' la lista resta pulita.
 */
class OrfaniTest extends TestCase
{
    /** Orfani noti, con il motivo. */
    private const KNOWN = [
        'routes' => [
            // Import guidato: solo l'account del .env, senza voce di menu (D3, 2026-10-08)
            'admin.federgolf-import.index',
        ],
        'views' => [],
        'actions' => [],
    ];

    public function test_no_new_orphan_routes(): void
    {
        $this->assertSame(self::KNOWN['routes'], (new OrphanFinder)->routesWithoutLinks());
    }

    public function test_no_new_orphan_views(): void
    {
        $this->assertSame(self::KNOWN['views'], (new OrphanFinder)->viewsNeverOpened());
    }

    public function test_no_controller_action_without_route(): void
    {
        $this->assertSame(self::KNOWN['actions'], (new OrphanFinder)->controllerActionsWithoutRoute());
    }
}
