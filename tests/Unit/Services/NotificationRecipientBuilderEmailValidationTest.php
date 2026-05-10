<?php

namespace Tests\Unit\Services;

use App\Services\NotificationRecipientBuilder;
use ReflectionClass;
use Tests\TestCase;

/**
 * Regressione per il fix "skip email malformate" in NotificationRecipientBuilder.
 *
 * Contesto: il 10 maggio 2026 il refactor Mail::raw → Mailable ha esposto un
 * dato corrotto in DB (zona con nome al posto dell'email). Symfony Mailer
 * crasha su address malformati. Il builder ora valida e skippa silenziosamente.
 *
 * Senza questo fix, un singolo record cattivo blocca l'intero invio nazionale.
 */
class NotificationRecipientBuilderEmailValidationTest extends TestCase
{
    /**
     * Helper: usa reflection per testare i metodi privati addTo/addCc senza
     * dover instanziare un torneo completo + relazioni.
     */
    private function invokePrivate(NotificationRecipientBuilder $builder, string $method, array $args): void
    {
        $reflection = new ReflectionClass($builder);
        $m = $reflection->getMethod($method);
        $m->setAccessible(true);
        $m->invoke($builder, ...$args);
    }

    public function test_invalid_email_is_skipped_in_to(): void
    {
        $builder = new NotificationRecipientBuilder;

        // Una stringa che è un nome, non un'email (caso reale: dato corrotto)
        $this->invokePrivate($builder, 'addTo', ['Sezione Zonale Regole 6', 'Nome Display']);

        $result = $builder->build();
        $this->assertEmpty($result['to'], 'Email malformate non devono finire in TO.');
    }

    public function test_invalid_email_is_skipped_in_cc(): void
    {
        $builder = new NotificationRecipientBuilder;

        $this->invokePrivate($builder, 'addCc', ['Sezione Zonale Regole 6', 'Nome Display']);
        $this->invokePrivate($builder, 'addCc', ['', 'Email vuota']);
        $this->invokePrivate($builder, 'addCc', ['valid@example.com', 'Indirizzo Valido']);

        $result = $builder->build();
        $this->assertCount(1, $result['cc'], 'Solo email valide devono finire in CC.');
        // Formato canonico: array<{email, name}>
        $this->assertEquals('valid@example.com', $result['cc'][0]['email']);
        $this->assertEquals('Indirizzo Valido', $result['cc'][0]['name']);
    }

    public function test_valid_email_is_kept(): void
    {
        $builder = new NotificationRecipientBuilder;

        $this->invokePrivate($builder, 'addTo', ['admin@federgolf.it', 'Admin']);
        $this->invokePrivate($builder, 'addCc', ['cc@federgolf.it', 'CC Recipient']);

        $result = $builder->build();
        $this->assertCount(1, $result['to']);
        $this->assertCount(1, $result['cc']);
    }
}
