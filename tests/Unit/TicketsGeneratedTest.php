<?php

namespace Tests\Unit;

use App\Mail\TicketsGenerated;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketsGeneratedTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();

        // S'assurer qu'on a bien les bonnes valeurs de config
        config()->set('app.frontend_url', 'https://frontend.test');
        putenv('APP_NAME=TestApp');
    }

    public function testBuildSetsSubjectViewAndWithData(): void
    {
        // Given
        config()->set('app.frontend_url', 'https://frontend.test');
        putenv('APP_NAME=TestApp');
        Storage::fake('tickets');

        $user = User::factory()->create();
        $tickets = collect([
            Ticket::factory()->make(['pdf_filename' => 't1.pdf']),
            Ticket::factory()->make(['pdf_filename' => 't2.pdf']),
        ]);

        // When
        $mailable = new TicketsGenerated($user, $tickets);
        $built    = $mailable->build();

        // Then: subject
        $expectedSubject = __('mail.tickets_generated_subject', ['app_name' => env('APP_NAME')]);
        $this->assertEquals($expectedSubject, $built->subject);

        // Reflexion pour récupérer les propriétés protégées
        $reflect = new \ReflectionClass($mailable);

        // 1) Vue
        $viewProp = $reflect->getProperty('view');
        $viewProp->setAccessible(true);
        $this->assertEquals(
            'emails.tickets.generated',
            $viewProp->getValue($mailable)
        );

        // 2) Données passées à la vue
        $dataProp = $reflect->getProperty('viewData');
        $dataProp->setAccessible(true);
        $viewData = $dataProp->getValue($mailable);

        $this->assertArrayHasKey('user', $viewData);
        $this->assertSame($user, $viewData['user']);

        $this->assertArrayHasKey('tickets', $viewData);
        // On compare simplement les pdf_filename pour s'assurer qu'ils correspondent
        $expectedFilenames = $tickets->pluck('pdf_filename')->all();
        $actualFilenames   = collect($viewData['tickets'])->pluck('pdf_filename')->all();
        $this->assertEquals($expectedFilenames, $actualFilenames);

        $this->assertArrayHasKey('clientUrl', $viewData);
        $this->assertEquals(
            'https://frontend.test/client/tickets',
            $viewData['clientUrl']
        );
    }

    public function testBuildAttachesEachTicketPdf(): void
    {
        // Given
        Storage::fake('tickets');
        $user = User::factory()->create();

        // Créez deux fichiers factices
        $filenames = ['t1.pdf', 't2.pdf'];
        foreach ($filenames as $name) {
            Storage::disk('tickets')->put($name, 'dummy content');
        }

        // Créez deux tickets avec ces noms
        $tickets = collect(array_map(fn($name) => Ticket::factory()->make(['pdf_filename' => $name]), $filenames));

        // When
        $mailable = (new TicketsGenerated($user, $tickets))->build();

        // Then: on a bien deux attachments
        $attachments = $mailable->attachments;
        $this->assertCount(2, $attachments);

        // Vérifie chaque attachement
        foreach ($attachments as $i => $attach) {
            // le chemin complet pointe bien sur notre fake disk
            $this->assertStringEndsWith($filenames[$i], $attach['file']);

            // le nom du fichier attaché (as) est dans options['as']
            $this->assertEquals(
                $filenames[$i],
                $attach['options']['as']
            );

            // le mime
            $this->assertEquals(
                'application/pdf',
                $attach['options']['mime']
            );

            // Et le fichier existe physiquement sur le disque fake
            $this->assertTrue(
                Storage::disk('tickets')->exists($filenames[$i]),
                "Le fichier {$filenames[$i]} devrait exister."
            );
        }
    }

}
