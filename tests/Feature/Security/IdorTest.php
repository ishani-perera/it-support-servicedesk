<?php

namespace Tests\Feature\Security;

use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAuthScenario;
use Tests\Concerns\BuildsTicketFixtures;
use Tests\Concerns\SeedsMasterData;
use Tests\TestCase;

/**
 * Insecure Direct Object Reference tests over REAL HTTP requests: the id in the
 * URL is attacker-controlled, so changing it must never widen access.
 */
class IdorTest extends TestCase
{
    use BuildsAuthScenario;
    use BuildsTicketFixtures;
    use RefreshDatabase;
    use SeedsMasterData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildAuthScenario();
        Storage::fake('local');
    }

    private function url(Ticket $ticket): string
    {
        return route('tickets.show', $ticket, absolute: false);
    }

    private function commentUrl(Ticket $ticket, TicketComment $comment): string
    {
        return route('tickets.comments.show', [$ticket, $comment], absolute: false);
    }

    private function attachmentUrl(Ticket $ticket, TicketAttachment $attachment): string
    {
        return route('tickets.attachments.show', [$ticket, $attachment], absolute: false);
    }

    private function putFile(TicketAttachment $attachment, string $contents = 'file-bytes'): void
    {
        Storage::disk('local')->put($attachment->file_path, $contents);
    }

    /* ===================== tickets ===================== */

    public function test_employee_a_can_view_ticket_a(): void
    {
        $this->actingAs($this->employeeA)->getJson($this->url($this->ticketA))
            ->assertOk()
            ->assertJsonPath('data.id', $this->ticketA->id)
            ->assertJsonPath('data.ticket_number', $this->ticketA->ticket_number);
    }

    public function test_employee_a_cannot_view_ticket_b(): void
    {
        $this->actingAs($this->employeeA)->getJson($this->url($this->ticketB))
            ->assertForbidden()
            ->assertJsonMissing(['title' => $this->ticketB->title])
            ->assertJsonMissing(['ticket_number' => $this->ticketB->ticket_number]);
    }

    public function test_changing_the_id_in_the_url_from_ticket_a_to_ticket_b_is_forbidden(): void
    {
        $this->actingAs($this->employeeA);

        // 1) the legitimate request ...
        $this->getJson('/tickets/'.$this->ticketA->id)->assertOk();

        // 2) ... then the attacker only edits the number in the URL.
        $this->getJson('/tickets/'.$this->ticketB->id)->assertForbidden();
        $this->getJson('/tickets/'.$this->ticketQueueB->id)->assertForbidden();
    }

    public function test_walking_every_existing_ticket_id_only_ever_returns_own_tickets(): void
    {
        $this->actingAs($this->employeeA);
        $own = [$this->ticketA->id, $this->ticketA2->id];

        foreach (Ticket::pluck('id') as $id) {
            $response = $this->getJson("/tickets/{$id}");

            in_array($id, $own, true) ? $response->assertOk() : $response->assertForbidden();
        }
    }

    public function test_a_nonexistent_id_never_returns_data(): void
    {
        $this->actingAs($this->employeeA)->getJson('/tickets/999999')->assertNotFound();
        $this->actingAs($this->employeeA)->getJson('/tickets/0')->assertNotFound();
        $this->actingAs($this->employeeA)->getJson('/tickets/-1')->assertNotFound();
        $this->actingAs($this->employeeA)->getJson('/tickets/abc')->assertNotFound();
    }

    public function test_an_existing_record_is_not_enough_to_get_access(): void
    {
        // Same call for an id that exists but is not yours: 403, never 200.
        $this->assertTrue(Ticket::whereKey($this->ticketB->id)->exists());
        $this->actingAs($this->employeeA)->get($this->url($this->ticketB))->assertForbidden();
    }

    public function test_support_sees_assigned_and_queue_tickets_but_not_a_colleagues(): void
    {
        $this->actingAs($this->supportOne);

        $this->getJson($this->url($this->ticketA))->assertOk();          // assigned to me
        $this->getJson($this->url($this->ticketQueueB))->assertOk();     // unassigned queue
        $this->getJson($this->url($this->ticketA2))->assertOk();         // unassigned queue
        $this->getJson($this->url($this->ticketB))->assertForbidden();   // held by supportTwo
    }

    public function test_admin_can_view_every_ticket(): void
    {
        $this->actingAs($this->admin);

        foreach (Ticket::pluck('id') as $id) {
            $this->getJson("/tickets/{$id}")->assertOk();
        }
    }

    public function test_a_ticket_loses_visibility_the_moment_it_is_reassigned(): void
    {
        $this->actingAs($this->supportOne)->getJson($this->url($this->ticketA))->assertOk();

        app(TicketAssignmentService::class)->assign($this->ticketA, $this->supportTwo, $this->admin);
        $this->app['auth']->forgetGuards();

        $this->actingAs($this->supportOne)->getJson($this->url($this->ticketA))->assertForbidden();
        $this->actingAs($this->supportTwo)->getJson($this->url($this->ticketA))->assertOk();
    }

    /* ===================== comments ===================== */

    public function test_employee_a_can_read_a_public_comment_on_their_own_ticket(): void
    {
        $this->actingAs($this->employeeA)
            ->getJson($this->commentUrl($this->ticketA, $this->publicA))
            ->assertOk()
            ->assertJsonPath('data.id', $this->publicA->id);
    }

    public function test_employee_a_cannot_read_employee_bs_comment(): void
    {
        $this->actingAs($this->employeeA)
            ->getJson($this->commentUrl($this->ticketB, $this->publicB))
            ->assertForbidden()
            ->assertJsonMissing(['body' => $this->publicB->body]);
    }

    public function test_pairing_my_ticket_with_someone_elses_comment_id_does_not_work(): void
    {
        // URL says "my ticket A" but names B's comment: scoped binding => 404, no data.
        $this->actingAs($this->employeeA)
            ->getJson($this->commentUrl($this->ticketA, $this->publicB))
            ->assertNotFound()
            ->assertJsonMissing(['body' => $this->publicB->body]);
    }

    public function test_employee_a_cannot_read_an_internal_note_on_their_own_ticket(): void
    {
        $this->actingAs($this->employeeA)
            ->getJson($this->commentUrl($this->ticketA, $this->internalA))
            ->assertForbidden()
            ->assertJsonMissing(['body' => $this->internalA->body]);
    }

    public function test_walking_every_comment_id_never_leaks_other_peoples_or_internal_comments(): void
    {
        $this->actingAs($this->employeeA);

        foreach (TicketComment::all() as $comment) {
            // The most favourable URL for the attacker: the comment's own, real ticket.
            $response = $this->getJson("/tickets/{$comment->ticket_id}/comments/{$comment->id}");

            $mine = $comment->ticket_id === $this->ticketA->id && ! $comment->is_internal;
            $mine ? $response->assertOk() : $response->assertForbidden();
        }
    }

    public function test_staff_comment_access_follows_ticket_access(): void
    {
        $this->actingAs($this->supportOne);
        $this->getJson($this->commentUrl($this->ticketA, $this->internalA))->assertOk();
        $this->getJson($this->commentUrl($this->ticketB, $this->internalB))->assertForbidden();

        $this->actingAs($this->admin);
        $this->getJson($this->commentUrl($this->ticketB, $this->internalB))->assertOk();
    }

    /* ===================== attachments ===================== */

    public function test_employee_a_can_download_their_own_attachment(): void
    {
        $this->putFile($this->attA, 'my-own-bytes');

        $response = $this->actingAs($this->employeeA)->get($this->attachmentUrl($this->ticketA, $this->attA));

        $response->assertOk()->assertDownload('report.pdf');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('my-own-bytes', $response->streamedContent());
    }

    public function test_employee_a_cannot_download_employee_bs_attachment(): void
    {
        $this->putFile($this->attB, 'secret-of-b');

        $response = $this->actingAs($this->employeeA)->get($this->attachmentUrl($this->ticketB, $this->attB));

        $response->assertForbidden();
        $this->assertStringNotContainsString('secret-of-b', $response->getContent());
    }

    public function test_pairing_my_ticket_with_someone_elses_attachment_id_does_not_work(): void
    {
        $this->putFile($this->attB, 'secret-of-b');

        $response = $this->actingAs($this->employeeA)->get($this->attachmentUrl($this->ticketA, $this->attB));

        $response->assertNotFound();
        $this->assertStringNotContainsString('secret-of-b', $response->getContent());
    }

    public function test_an_attachment_on_an_internal_note_is_hidden_from_the_requester(): void
    {
        $this->putFile($this->attInternalA, 'staff-only');

        $this->actingAs($this->employeeA)->get($this->attachmentUrl($this->ticketA, $this->attInternalA))->assertForbidden();
        $this->actingAs($this->supportOne)->get($this->attachmentUrl($this->ticketA, $this->attInternalA))->assertOk();
    }

    public function test_unauthorized_requests_get_403_even_when_the_file_does_not_exist(): void
    {
        // Authorization runs BEFORE the file lookup, so 403 vs 404 cannot be used to probe for files.
        $this->actingAs($this->employeeA)->get($this->attachmentUrl($this->ticketB, $this->attB))->assertForbidden();
        // ... whereas an authorized request for a missing file is a plain 404.
        $this->actingAs($this->employeeB)->get($this->attachmentUrl($this->ticketB, $this->attB))->assertNotFound();
    }

    public function test_staff_attachment_access_follows_ticket_access(): void
    {
        $this->putFile($this->attA);
        $this->putFile($this->attB);

        $this->actingAs($this->supportOne)->get($this->attachmentUrl($this->ticketA, $this->attA))->assertOk();
        $this->actingAs($this->supportOne)->get($this->attachmentUrl($this->ticketB, $this->attB))->assertForbidden();
        $this->actingAs($this->supportTwo)->get($this->attachmentUrl($this->ticketA, $this->attA))->assertForbidden();
        $this->actingAs($this->admin)->get($this->attachmentUrl($this->ticketB, $this->attB))->assertOk();
    }

    public function test_the_private_disk_is_not_reachable_through_a_framework_url(): void
    {
        $this->putFile($this->attB, 'secret-of-b');

        $this->assertFalse(app('router')->has('storage.local'), 'the built-in private-disk route must stay disabled');
        $this->actingAs($this->employeeA);
        $this->get('/storage/'.$this->attB->file_path)->assertNotFound();
        $this->get('/storage/tickets/'.$this->ticketB->id.'/'.$this->attB->file_name)->assertNotFound();
    }

    public function test_attachment_storage_paths_are_never_exposed_in_responses(): void
    {
        $this->assertArrayNotHasKey('file_path', $this->attA->toArray());
        $this->assertArrayNotHasKey('file_name', $this->attA->toArray());
    }

    /* ===================== users / assignments ===================== */

    public function test_employee_cannot_reach_other_user_records_through_any_user_endpoint(): void
    {
        $this->actingAs($this->employeeA);

        $this->getJson('/admin/users')->assertForbidden();
        $this->patchJson('/admin/users/'.$this->employeeB->id, ['name' => 'x', 'role' => 'admin'])->assertForbidden();
        $this->patchJson('/admin/users/'.$this->admin->id, ['is_active' => false])->assertForbidden();
        $this->patchJson('/admin/users/999999', ['role' => 'admin'])->assertForbidden(); // role middleware answers first

        $this->assertSame(UserRole::Employee, $this->employeeB->fresh()->role);
        $this->assertTrue($this->admin->fresh()->is_active);
    }

    public function test_the_profile_endpoint_has_no_user_id_to_tamper_with(): void
    {
        $before = $this->employeeB->only(['name', 'phone']);

        $this->actingAs($this->employeeA)->patchJson('/profile', [
            'id' => $this->employeeB->id,
            'user_id' => $this->employeeB->id,
            'name' => 'Renamed By Self',
            'phone' => '+94 77 000 0000',
        ])->assertNoContent();

        $this->assertSame('Renamed By Self', $this->employeeA->fresh()->name);
        $this->assertSame($before, $this->employeeB->fresh()->only(['name', 'phone']));
    }

    /* ===================== sweep over the real demo data ===================== */

    public function test_every_demo_employee_sees_only_their_own_demo_tickets(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tickets = Ticket::all();
        $this->assertGreaterThanOrEqual(26, $tickets->count());

        $employees = User::where('role', UserRole::Employee)->get();
        $this->assertGreaterThanOrEqual(8, $employees->count());

        foreach ($employees as $employee) {
            $this->actAs($employee);

            foreach ($tickets as $ticket) {
                $response = $this->getJson("/tickets/{$ticket->id}");

                $ticket->user_id === $employee->id ? $response->assertOk() : $response->assertForbidden();
            }
        }
    }

    public function test_demo_support_agents_and_admin_match_the_policy_over_the_whole_dataset(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (User::where('role', UserRole::Support)->get() as $agent) {
            $expected = Ticket::query()->visibleTo($agent)->pluck('id')->all();
            $this->assertNotEmpty($expected);
            $this->actAs($agent);

            foreach (Ticket::pluck('id') as $id) {
                $response = $this->getJson("/tickets/{$id}");

                in_array($id, $expected, true) ? $response->assertOk() : $response->assertForbidden();
            }
        }

        $admin = User::where('role', UserRole::Admin)->firstOrFail();
        $this->actAs($admin);
        foreach (Ticket::pluck('id') as $id) {
            $this->getJson("/tickets/{$id}")->assertOk();
        }
    }

    public function test_demo_internal_notes_are_never_served_to_their_requesters(): void
    {
        $this->seed(DatabaseSeeder::class);

        $internal = TicketComment::where('is_internal', true)->get();
        $this->assertGreaterThan(0, $internal->count());

        foreach ($internal as $comment) {
            $requester = User::findOrFail(Ticket::findOrFail($comment->ticket_id)->user_id);

            $this->actAs($requester)
                ->getJson("/tickets/{$comment->ticket_id}/comments/{$comment->id}")
                ->assertForbidden();
        }
    }
}
