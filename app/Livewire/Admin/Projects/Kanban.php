<?php

namespace App\Livewire\Admin\Projects;

use App\Enums\ActivityAction;
use App\Enums\TicketStatus;
use App\Models\RevisionTicket;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::admin')]
class Kanban extends Component
{
    public function mount(): void
    {
        $this->authorize('access-internal');
    }

    public function setTicketStatus(string $ticketId, string $to): void
    {
        $user = auth()->user();
        $ticket = RevisionTicket::visibleTo($user)->with('project')->findOrFail($ticketId);
        $this->authorize('updateStatus', $ticket);
        $target = TicketStatus::tryFrom($to);
        abort_if($target === null, 422);

        DB::transaction(function () use ($ticketId, $target, $user): void {
            $ticket = RevisionTicket::visibleTo($user)->with('project')->lockForUpdate()->findOrFail($ticketId);
            $this->authorize('updateStatus', $ticket);
            $from = $ticket->status;
            if ($from === $target) {
                return;
            }

            $ticket->forceFill([
                'status' => $target,
                'resolved_at' => in_array($target, [TicketStatus::Resolved, TicketStatus::Closed], true) ? now() : null,
            ])->save();
            ActivityLogger::changed(ActivityAction::TicketStatusChanged, $ticket, $from, $target, $user);
        });

        Flux::toast(variant: 'success', text: __('admin.kanban.updated'));
    }

    /** Reorder a status column or move authorized tickets to another column. */
    public function reorderTickets(string $status, array $ticketIds): void
    {
        $user = auth()->user();
        $target = TicketStatus::tryFrom($status);
        abort_if($target === null || count($ticketIds) > 500, 422);

        $ticketIds = array_values($ticketIds);
        abort_if(count($ticketIds) !== count(array_unique($ticketIds)), 422);

        DB::transaction(function () use ($user, $target, $ticketIds): void {
            $tickets = RevisionTicket::visibleTo($user)
                ->with('project')
                ->whereIn('id', $ticketIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            abort_unless($tickets->count() === count($ticketIds), 404);

            foreach ($ticketIds as $position => $ticketId) {
                $ticket = $tickets->get($ticketId);
                $this->authorize('updateStatus', $ticket);
                $from = $ticket->status;
                $ticket->forceFill([
                    'status' => $target,
                    'sort_order' => $position,
                    'resolved_at' => $from === $target
                        ? $ticket->resolved_at
                        : (in_array($target, [TicketStatus::Resolved, TicketStatus::Closed], true) ? now() : null),
                ])->save();

                if ($from !== $target) {
                    ActivityLogger::changed(ActivityAction::TicketStatusChanged, $ticket, $from, $target, $user);
                }
            }
        });

        Flux::toast(variant: 'success', text: __('admin.kanban.updated'));
    }

    public function render(): View
    {
        $user = auth()->user();
        $this->authorize('access-internal');
        $columns = [];

        foreach (TicketStatus::kanbanColumns() as $status) {
            $columns[$status->value] = RevisionTicket::visibleTo($user)
                ->where('status', $status->value)
                ->with([
                    'project' => fn ($query) => $query->visibleTo($user)->with('client:id,name'),
                    'creator:id,name',
                ])
                ->withCount('comments')
                ->orderBy('sort_order')
                ->orderBy('created_at')
                ->get();
        }

        return view('livewire.admin.projects.kanban', [
            'columns' => $columns,
            'statuses' => TicketStatus::kanbanColumns(),
        ])->title(__('admin.nav.kanban'));
    }
}
