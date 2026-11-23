import Sortable from 'sortablejs';

const initializeTicketBoards = () => {
    document.querySelectorAll('[data-ticket-board]').forEach((board) => {
        if (board.dataset.sortableReady === 'true') return;
        board.dataset.sortableReady = 'true';

        new Sortable(board, {
            animation: 160,
            draggable: '[data-ticket-id]',
            handle: '[data-drag-handle]',
            group: 'revision-tickets',
            ghostClass: 'opacity-40',
            onEnd: (event) => {
                const targetBoard = event.to;
                const componentRoot = targetBoard.closest('[wire\\:id]');
                const componentId = componentRoot?.getAttribute('wire:id');
                const component = componentId ? window.Livewire?.find(componentId) : null;

                if (!component) return;

                const ticketIds = [...targetBoard.querySelectorAll('[data-ticket-id]')]
                    .map((ticket) => ticket.dataset.ticketId);

                component.call('reorderTickets', targetBoard.dataset.status, ticketIds);
            },
        });
    });
};

document.addEventListener('livewire:init', initializeTicketBoards);
document.addEventListener('livewire:navigated', initializeTicketBoards);
