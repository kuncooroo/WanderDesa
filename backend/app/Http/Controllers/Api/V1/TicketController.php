<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Tickets\RecordTicketPrintAttempt;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tickets\PrintAckRequest;
use App\Http\Resources\Api\V1\TicketResource;
use App\Models\Device;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\DeviceAbilities;
use App\Support\Tickets\TicketAccess;
use App\Support\Tickets\TicketPrintPayload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    public function indexForOrder(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrderView($request, $order);

        $order->loadMissing(['tickets.order']);

        return ApiResponse::success(
            TicketResource::collection($order->tickets)->resolve($request),
        );
    }

    public function show(Request $request, string $ticket_code): JsonResponse
    {
        $ticket = $this->findTicketOrNotFound($ticket_code);
        $this->authorizeTicketView($request, $ticket);

        $ticket->loadMissing('order');

        return ApiResponse::success(
            (new TicketResource($ticket))->resolve($request),
        );
    }

    public function printPayload(Request $request, string $ticket_code): JsonResponse
    {
        $ticket = $this->findTicketOrNotFound($ticket_code);
        $this->authorizeTicketPrint($request, $ticket);

        $ticket->loadMissing(['destination', 'ticketType', 'orderItem', 'order']);

        return ApiResponse::success(TicketPrintPayload::fromTicket($ticket));
    }

    public function printAck(
        PrintAckRequest $request,
        string $ticket_code,
        RecordTicketPrintAttempt $record,
    ): JsonResponse {
        $ticket = $this->findTicketOrNotFound($ticket_code);
        $principal = $this->requirePrintPrincipal($request);
        $attempt = $record->handle(
            $principal,
            $ticket,
            $request->result(),
            $request->message(),
            $request,
        );

        $ticket->refresh();
        $status = $ticket->status;
        $log = $attempt->log;

        return ApiResponse::success([
            'ticket_code' => $ticket->ticket_code,
            'result' => $log->result instanceof \BackedEnum ? $log->result->value : $log->result,
            'audit_action' => $attempt->auditAction,
            'print_log_id' => $log->id,
            'ticket_status' => $status instanceof \BackedEnum ? $status->value : $status,
        ], status: 201);
    }

    private function requirePrintPrincipal(Request $request): Device|User
    {
        $principal = $request->user();

        if ($principal instanceof Device || $principal instanceof User) {
            return $principal;
        }

        throw new AuthorizationException('Unauthenticated ticket principal.');
    }

    private function findTicketOrNotFound(string $code): Ticket
    {
        $ticket = Ticket::query()->where('ticket_code', $code)->first();

        if ($ticket === null) {
            throw new DomainException(
                'resource.not_found',
                'Ticket not found.',
                404,
            );
        }

        return $ticket;
    }

    private function authorizeOrderView(Request $request, Order $order): void
    {
        $principal = $request->user();

        if ($principal instanceof Device) {
            if ((int) $order->device_id !== (int) $principal->getKey()) {
                throw new DomainException(
                    'resource.not_found',
                    'Order not found.',
                    404,
                );
            }

            if (
                ! $principal->tokenCan(DeviceAbilities::TICKETS_READ_OWN)
                && ! $principal->tokenCan(DeviceAbilities::TICKETS_PRINT_OWN)
            ) {
                throw new AuthorizationException('Missing device ability: tickets:read_own');
            }

            return;
        }

        if ($principal instanceof User) {
            $this->authorize('view', $order);

            return;
        }

        throw new AuthorizationException('Unauthenticated ticket principal.');
    }

    private function authorizeTicketView(Request $request, Ticket $ticket): void
    {
        $principal = $request->user();
        $ticket->loadMissing('order');

        if ($principal instanceof Device) {
            if (
                ! $principal->tokenCan(DeviceAbilities::TICKETS_READ_OWN)
                && ! $principal->tokenCan(DeviceAbilities::TICKETS_PRINT_OWN)
            ) {
                throw new AuthorizationException('Missing device ability: tickets:read_own');
            }

            if (! TicketAccess::deviceOwns($principal, $ticket)) {
                throw new DomainException(
                    'resource.not_found',
                    'Ticket not found.',
                    404,
                );
            }

            return;
        }

        if ($principal instanceof User) {
            $this->authorize('view', $ticket);

            return;
        }

        throw new AuthorizationException('Unauthenticated ticket principal.');
    }

    private function authorizeTicketPrint(Request $request, Ticket $ticket): void
    {
        $principal = $request->user();
        $ticket->loadMissing('order');

        if ($principal instanceof Device) {
            if (
                ! $principal->tokenCan(DeviceAbilities::TICKETS_PRINT_OWN)
                && ! $principal->tokenCan(DeviceAbilities::TICKETS_READ_OWN)
            ) {
                throw new AuthorizationException('Missing device ability: tickets:print_own');
            }

            if (! TicketAccess::deviceOwns($principal, $ticket)) {
                throw new DomainException(
                    'resource.not_found',
                    'Ticket not found.',
                    404,
                );
            }

            return;
        }

        if ($principal instanceof User) {
            if (! $principal->can('print', $ticket) && ! $principal->can('reprint', $ticket)) {
                throw new AuthorizationException('This action is unauthorized.');
            }

            return;
        }

        throw new AuthorizationException('Unauthenticated ticket principal.');
    }
}
