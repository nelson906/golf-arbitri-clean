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

    /**
     * Regressione critica: il CC array DEVE essere in formato canonico Laravel
     * `array<{email: string, name: string}>` con chiavi numeriche sequenziali.
     *
     * Il vecchio formato `[email => name]` (chiavi associative con email come
     * stringa) funzionava SOLO con `Mail::raw + closure` (Symfony Message::cc
     * accettava entrambi i formati). Con `Mail::to()->cc()->send(Mailable)`,
     * il PendingMail::parseAddresses() itera l'array via `collect()->map()`
     * sui VALUES, quindi un nome come "Sezione Zonale Regole 6" finirebbe a
     * `Symfony\Address::create()` come stringa-email e crasha RFC 2822.
     *
     * Se questo test rompe, qualcuno ha rivertito build() al formato vecchio.
     */
    public function test_build_returns_cc_in_canonical_laravel_format(): void
    {
        $builder = new NotificationRecipientBuilder;
        $this->invokePrivate($builder, 'addCc', ['user1@example.com', 'User Uno']);
        $this->invokePrivate($builder, 'addCc', ['user2@example.com', 'User Due']);

        $cc = $builder->build()['cc'];

        // Deve essere un array sequenziale (chiavi numeriche), non associativo
        $this->assertSame([0, 1], array_keys($cc), 'CC deve avere chiavi numeriche sequenziali, non email come chiavi.');

        // Ogni voce deve avere chiavi 'email' e 'name'
        foreach ($cc as $entry) {
            $this->assertIsArray($entry);
            $this->assertArrayHasKey('email', $entry);
            $this->assertArrayHasKey('name', $entry);
        }
    }

    /**
     * Regressione di integrazione: il CC array prodotto da build() deve essere
     * direttamente consumabile da `Mail::cc()` senza errore "addr-spec RFC 2822".
     *
     * Riproduce il bug del 10 maggio 2026: Symfony riceveva il NAME come email
     * perché il formato `[email => name]` veniva iterato sui VALUES.
     */
    public function test_cc_array_is_consumable_by_mail_cc(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $builder = new NotificationRecipientBuilder;
        $this->invokePrivate($builder, 'addTo', ['to@example.com', 'TO Recipient']);
        // Nome con spazi e caratteri tipici di una zona, deve essere tollerato
        $this->invokePrivate($builder, 'addCc', ['szr6@federgolf.it', 'Sezione Zonale Regole 6']);

        $recipients = $builder->build();

        // Questo è esattamente il pattern usato in NotificationController
        $mailable = new \App\Mail\NationalNotificationMail('Subject Test', 'Body Test');

        // Se il formato CC fosse sbagliato, Mail::to()->cc()->send() lancerebbe
        // un'eccezione "Email \"Sezione Zonale Regole 6\" does not comply with addr-spec".
        \Illuminate\Support\Facades\Mail::to($recipients['to'][0]['email'])
            ->cc($recipients['cc'])
            ->send($mailable);

        \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\NationalNotificationMail::class, 1);
    }
}
