<?php

namespace App\Livewire\Tickets;

use App\Actions\Tickets\RecordTicketPrintAttempt;
use App\Enums\PermissionName;
use App\Enums\PrintAttemptResult;
use App\Enums\TicketStatus;
use App\Exceptions\DomainException;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Authorization\Authorizer;
use App\Support\Tickets\TicketPrintPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Staff ticket lookup + audited reprint. Payload comes from Laravel; no new ticket.
 */
#[Layout('layouts.app')]
#[Title('Tiket')]
class TicketDesk extends Component
{
    use AuthorizesRequests;

    public string $ticketCode = '';

    public string $errorMessage = '';

    public string $flashMessage = '';

    /** @var array<string, mixed>|null */
    public ?array $ticket = null;

    /** @var array<string, mixed>|null */
    public ?array $printPayload = null;

    public bool $alreadyPrinted = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Ticket::class);
    }

    public function searchTicket(RecordTicketPrintAttempt $record): void
    {
        $this->authorize('viewAny', Ticket::class);
        $this->errorMessage = '';
        $this->flashMessage = '';
        $this->printPayload = null;

        $code = strtoupper(trim($this->ticketCode));
        if ($code === '') {
            $this->errorMessage = 'Masukkan kode tiket.';
            $this->ticket = null;

            return;
        }

        $found = Ticket::query()
            ->with(['destination', 'ticketType', 'orderItem', 'order'])
            ->where('ticket_code', $code)
            ->first();

        if ($found === null) {
            $this->ticket = null;
            $this->errorMessage = 'Tiket tidak ditemukan.';

            return;
        }

        $this->authorize('view', $found);
        $this->ticketCode = $found->ticket_code;
        $this->alreadyPrinted = $record->hasSuccessfulPrint($found);
        $this->ticket = $this->summarize($found);
    }

    public function loadPrintPayload(): void
    {
        $found = $this->requireLoadedTicket();
        if ($found === null) {
            return;
        }

        $this->assertCanFetchPayload($found);
        $this->printPayload = TicketPrintPayload::fromTicket($found);
        $this->flashMessage = '';
        $this->errorMessage = '';
    }

    public function recordPrintSuccess(RecordTicketPrintAttempt $record): void
    {
        $this->recordAttempt($record, PrintAttemptResult::Success, null);
    }

    public function recordPrintFailure(RecordTicketPrintAttempt $record): void
    {
        $this->recordAttempt($record, PrintAttemptResult::Failed, 'dashboard_print_failed');
    }

    private function recordAttempt(
        RecordTicketPrintAttempt $record,
        PrintAttemptResult $result,
        ?string $message,
    ): void {
        $found = $this->requireLoadedTicket();
        if ($found === null) {
            return;
        }

        $statusBefore = $found->status instanceof TicketStatus
            ? $found->status->value
            : (string) $found->status;

        try {
            $attempt = $record->handle($this->staff(), $found, $result, $message, request());
            $found->refresh();
            $this->alreadyPrinted = $record->hasSuccessfulPrint($found);
            $this->ticket = $this->summarize($found);
            $this->printPayload = TicketPrintPayload::fromTicket($found);
            $this->flashMessage = match ($attempt->auditAction) {
                'ticket.reprinted' => 'Cetak ulang dicatat. Tiket tidak diterbitkan ulang.',
                'ticket.print_failed' => 'Kegagalan cetak dicatat. Tiket tetap berlaku.',
                default => 'Cetak dicatat. Tiket tidak berubah.',
            };
            $this->errorMessage = '';

            $statusAfter = $found->status instanceof TicketStatus
                ? $found->status->value
                : (string) $found->status;
            if ($statusBefore !== $statusAfter) {
                $this->errorMessage = 'Status tiket berubah tanpa sengaja.';
            }
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function canPrint(): bool
    {
        return Authorizer::check($this->staff(), PermissionName::TicketsPrint);
    }

    public function canReprint(): bool
    {
        return Authorizer::check($this->staff(), PermissionName::TicketsReprint);
    }

    public function render()
    {
        return view('livewire.tickets.ticket-desk');
    }

    private function requireLoadedTicket(): ?Ticket
    {
        if ($this->ticket === null) {
            $this->errorMessage = 'Cari tiket terlebih dahulu.';

            return null;
        }

        $found = Ticket::query()
            ->with(['destination', 'ticketType', 'orderItem', 'order'])
            ->find($this->ticket['id'] ?? null);

        if ($found === null) {
            $this->ticket = null;
            $this->errorMessage = 'Tiket tidak ditemukan.';

            return null;
        }

        $this->authorize('view', $found);

        return $found;
    }

    private function assertCanFetchPayload(Ticket $ticket): void
    {
        if ($this->staff()->can('print', $ticket) || $this->staff()->can('reprint', $ticket)) {
            return;
        }

        throw new AuthorizationException('This action is unauthorized.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Ticket $ticket): array
    {
        $status = $ticket->status;

        return [
            'id' => $ticket->id,
            'ticket_code' => $ticket->ticket_code,
            'status' => $status instanceof TicketStatus ? $status->value : (string) $status,
            'destination' => $ticket->destination?->name,
            'ticket_type' => $ticket->ticketType?->name ?? $ticket->orderItem?->ticket_type_name,
            'valid_start_at' => optional($ticket->valid_start_at)?->toIso8601String(),
            'valid_end_at' => optional($ticket->valid_end_at)?->toIso8601String(),
            'order_id' => $ticket->order_id,
        ];
    }

    private function staff(): User
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            throw new AuthorizationException('Unauthenticated.');
        }

        return $user;
    }
}
