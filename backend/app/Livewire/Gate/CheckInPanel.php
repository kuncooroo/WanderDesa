<?php

namespace App\Livewire\Gate;

use App\Actions\CheckIns\CheckInTicket;
use App\Enums\PermissionName;
use App\Enums\TicketDenyCode;
use App\Enums\TicketValidateResult;
use App\Exceptions\DomainException;
use App\Models\Destination;
use App\Models\Gate;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Qr\QrPayloadGenerator;
use App\Support\Authorization\Authorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Gate desk scanner + manual entry. Input only — CheckInTicket decides ALLOW/DENY.
 */
#[Layout('layouts.app')]
#[Title('Check-in gerbang')]
class CheckInPanel extends Component
{
    use AuthorizesRequests;

    public ?int $destinationId = null;

    public ?int $gateId = null;

    public string $qrPayload = '';

    public string $entryMode = 'scan';

    public string $result = '';

    public string $reasonCode = '';

    public string $ticketCode = '';

    public string $ticketStatus = '';

    public string $errorMessage = '';

    public string $idempotencyKey = '';

    public bool $busy = false;

    public function mount(): void
    {
        Authorizer::authorize($this->staff(), PermissionName::CheckinsCreate);
        $this->idempotencyKey = (string) Str::uuid();
    }

    public function updatedDestinationId(): void
    {
        $this->gateId = null;
        $this->clearResult();
        $this->focusScanField();
    }

    public function useManualEntry(): void
    {
        Authorizer::authorize($this->staff(), PermissionName::CheckinsCreate);
        $this->entryMode = 'manual';
        $this->errorMessage = '';
        $this->qrPayload = '';
        $this->focusScanField();
    }

    public function useScanEntry(): void
    {
        Authorizer::authorize($this->staff(), PermissionName::CheckinsCreate);
        $this->entryMode = 'scan';
        $this->errorMessage = '';
        $this->qrPayload = '';
        $this->focusScanField();
    }

    public function submitCheckIn(CheckInTicket $checkIn, QrPayloadGenerator $qr): void
    {
        Authorizer::authorize($this->staff(), PermissionName::CheckinsCreate);

        if ($this->busy) {
            return;
        }

        $this->errorMessage = '';
        $raw = $this->normalizeInput($this->qrPayload);

        if ($this->destinationId === null || $raw === '') {
            $this->errorMessage = $this->destinationId === null
                ? 'Pilih destinasi, lalu scan QR atau masukkan kode tiket.'
                : 'QR atau kode tiket wajib diisi.';
            $this->focusScanField();

            return;
        }

        $this->busy = true;

        try {
            $payload = $this->resolvePayload($raw, $qr);

            $outcome = $checkIn->handle(
                $this->staff(),
                $payload,
                (int) $this->destinationId,
                $this->idempotencyKey,
                $this->gateId,
                request(),
            );

            $this->result = $outcome->result->value;
            $this->reasonCode = $outcome->reasonCode?->value ?? '';
            $this->ticketCode = $outcome->ticket?->ticket_code ?? '';
            $this->ticketStatus = $outcome->ticket?->status?->value
                ?? (string) ($outcome->ticket?->status ?? '');
            $this->qrPayload = '';
            $this->idempotencyKey = (string) Str::uuid();
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (DomainException $e) {
            $this->errorMessage = $e->getMessage();
            $this->idempotencyKey = (string) Str::uuid();
        } finally {
            $this->busy = false;
            $this->focusScanField();
        }
    }

    public function clearResult(): void
    {
        $this->result = '';
        $this->reasonCode = '';
        $this->ticketCode = '';
        $this->ticketStatus = '';
        $this->errorMessage = '';
        $this->focusScanField();
    }

    public function reasonLabel(): string
    {
        $code = TicketDenyCode::tryFrom($this->reasonCode);

        return match ($code) {
            TicketDenyCode::AlreadyUsed => 'Tiket sudah digunakan sebelumnya.',
            TicketDenyCode::InvalidAuth => 'QR tidak valid.',
            TicketDenyCode::NotFound => 'Tiket tidak ditemukan.',
            TicketDenyCode::WrongDestination => 'Tiket bukan untuk destinasi ini.',
            TicketDenyCode::NotPaid => 'Pembayaran belum sah.',
            TicketDenyCode::NotActive => 'Tiket belum aktif.',
            TicketDenyCode::Expired => 'Tiket kedaluwarsa.',
            TicketDenyCode::Cancelled => 'Tiket dibatalkan.',
            TicketDenyCode::Refunded => 'Tiket sudah direfund.',
            TicketDenyCode::Unauthorized => 'Petugas tidak berwenang untuk tiket ini.',
            default => $this->reasonCode,
        };
    }

    public function render()
    {
        $destinations = Destination::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $gates = $this->destinationId === null
            ? collect()
            : Gate::query()
                ->where('destination_id', $this->destinationId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']);

        $isAllow = $this->result === TicketValidateResult::Allow->value;
        $isDeny = $this->result === TicketValidateResult::Deny->value;

        return view('livewire.gate.check-in-panel', [
            'destinations' => $destinations,
            'gates' => $gates,
            'isAllow' => $isAllow,
            'isDeny' => $isDeny,
        ]);
    }

    private function normalizeInput(string $raw): string
    {
        $trimmed = trim(str_replace(["\r", "\n"], '', $raw));

        return mb_substr($trimmed, 0, 512);
    }

    private function resolvePayload(string $raw, QrPayloadGenerator $qr): string
    {
        if ($qr->parse($raw) !== null) {
            return $raw;
        }

        if (! $this->looksLikeTicketCode($raw)) {
            return $raw;
        }

        $ticket = Ticket::query()
            ->whereRaw('UPPER(ticket_code) = ?', [strtoupper($raw)])
            ->first();

        return $ticket?->qr_payload ?? $raw;
    }

    private function looksLikeTicketCode(string $raw): bool
    {
        if ($this->entryMode === 'manual') {
            return true;
        }

        return (bool) preg_match('/^TCK-[A-Za-z0-9]+$/i', $raw);
    }

    private function focusScanField(): void
    {
        $this->dispatch('gate-scan-focus');
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
