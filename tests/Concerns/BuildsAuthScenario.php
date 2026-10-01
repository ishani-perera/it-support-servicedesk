<?php

namespace Tests\Concerns;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\User;

/**
 * A small, fully known authorization world (deterministic, no demo data):
 *
 *   employeeA  requested ticketA  (held by supportOne)   + ticketA2 (unassigned queue)
 *   employeeB  requested ticketB  (held by supportTwo)   + ticketQueueB (unassigned queue)
 *
 *   comments:    publicA, internalA (on ticketA) · publicB, internalB (on ticketB)
 *   attachments: attA (ticketA, plain) · attInternalA (ticketA, on internalA) · attB (ticketB)
 *
 * Requires BuildsTicketFixtures + SeedsMasterData in the using class.
 */
trait BuildsAuthScenario
{
    protected User $employeeA;

    protected User $employeeB;

    protected User $supportOne;

    protected User $supportTwo;

    protected User $admin;

    protected Ticket $ticketA;

    protected Ticket $ticketA2;

    protected Ticket $ticketB;

    protected Ticket $ticketQueueB;

    protected TicketComment $publicA;

    protected TicketComment $internalA;

    protected TicketComment $publicB;

    protected TicketComment $internalB;

    protected TicketAttachment $attA;

    protected TicketAttachment $attInternalA;

    protected TicketAttachment $attB;

    protected function buildAuthScenario(): void
    {
        $this->seedMasterData();

        // The login/reset pages use @vite(); tests must not depend on a built manifest.
        $this->withoutVite();

        $it = Department::where('name', 'IT')->firstOrFail();
        $finance = Department::where('name', 'Finance')->firstOrFail();

        $this->employeeA = $this->makeUser(UserRole::Employee, $finance);
        $this->employeeB = $this->makeUser(UserRole::Employee, $finance);
        $this->supportOne = $this->makeUser(UserRole::Support, $it);
        $this->supportTwo = $this->makeUser(UserRole::Support, $it);
        $this->admin = $this->makeUser(UserRole::Admin, $it);

        $this->ticketA = $this->makeTicket($this->employeeA);
        $this->ticketA2 = $this->makeTicket($this->employeeA);
        $this->ticketB = $this->makeTicket($this->employeeB);
        $this->ticketQueueB = $this->makeTicket($this->employeeB);

        $this->makeAssignment($this->ticketA, $this->supportOne, $this->admin);
        $this->makeAssignment($this->ticketB, $this->supportTwo, $this->admin);

        $this->publicA = $this->makeComment($this->ticketA, $this->employeeA);
        $this->internalA = $this->makeComment($this->ticketA, $this->supportOne, internal: true);
        $this->publicB = $this->makeComment($this->ticketB, $this->employeeB);
        $this->internalB = $this->makeComment($this->ticketB, $this->supportTwo, internal: true);

        $this->attA = $this->makeAttachment($this->ticketA, $this->employeeA);
        $this->attInternalA = $this->makeAttachment($this->ticketA, $this->supportOne, $this->internalA);
        $this->attB = $this->makeAttachment($this->ticketB, $this->employeeB);
    }

    /**
     * Act as $user with a clean slate. Real browsers each have their own session
     * and every request is a fresh PHP process; inside one test process the
     * session store and the guard persist, so switching users needs a reset
     * (otherwise the previous user's stored password hash — which the
     * AuthenticateSession middleware checks — would log the next user out).
     */
    protected function actAs(User $user): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return $this->actingAs($user);
    }
}
