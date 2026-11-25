<?php

return [
    'heading' => 'Next step',
    'resubmit_payment' => [
        'title' => 'Payment proof rejected, please resubmit',
        'description' => 'We could not accept the payment for invoice :number. See the reason, then upload the correct proof.',
        'label' => 'Resubmit proof',
    ],
    'pay_overdue' => [
        'title' => 'Invoice is overdue',
        'description' => 'Invoice :number for :amount is past its due date. Please pay as soon as possible.',
        'label' => 'Pay now',
    ],
    'pay_dp' => [
        'title' => 'Pay the down payment to start the project',
        'description' => 'Invoice :number for :amount is awaiting payment. The project starts once we verify your down payment.',
        'label' => 'Pay down payment',
    ],
    'pay_final' => [
        'title' => 'Pay the final balance',
        'description' => 'Invoice :number for :amount is awaiting payment so the project can be handed over.',
        'label' => 'Pay final balance',
    ],
    'pay_invoice' => [
        'title' => 'An invoice is waiting for payment',
        'description' => 'Invoice :number for :amount is awaiting payment.',
        'label' => 'View invoices',
    ],
    'payment_verifying' => [
        'title' => 'Payment proof is being verified',
        'description' => 'We are checking the payment for invoice :number. There is no need to send it again.',
        'label' => 'View status',
    ],
    'review_quotation' => [
        'title' => 'Quotation awaiting your approval',
        'description' => 'Review the proposal, then approve it or request changes in the chat.',
        'label' => 'Review quotation',
    ],
    'review_quotation_soon' => [
        'title' => 'Quotation is about to expire',
        'description' => 'The proposal is valid for :days more days. Review and approve it before it ends.',
        'label' => 'Review quotation',
    ],
    'review_quotation_today' => [
        'title' => 'Quotation expires today',
        'description' => 'The proposal expires today. Review and approve it before it is too late.',
        'label' => 'Review quotation',
    ],
    'quotation_expired' => [
        'title' => 'Quotation has expired',
        'description' => 'The proposal is no longer valid. Contact us in the chat to request an updated one.',
        'label' => 'Open chat',
    ],
    'awaiting_revision' => [
        'title' => 'Waiting for the updated proposal',
        'description' => 'Our team will send a new version based on your discussion. You can keep talking in the chat.',
        'label' => 'Open chat',
    ],
    'awaiting_quotation' => [
        'title' => 'Quotation is being prepared',
        'description' => 'Our team is preparing a proposal for this project. Ask in the chat if you have questions.',
        'label' => 'Open chat',
    ],
    'handover_ready' => [
        'title' => 'Project is ready for handover',
        'description' => 'The work is complete. Our team will contact you about the handover.',
        'label' => 'View project',
    ],
    'on_hold' => [
        'title' => 'Project is on hold',
        'description' => 'Work is paused for now. Our team will let you know when it resumes.',
        'label' => '',
    ],
];
