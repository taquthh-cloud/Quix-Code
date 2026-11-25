<?php

return [
    'greeting' => 'Hello :name,',
    'salutation' => 'Regards, The Quix Code Team',

    'quotation_sent' => [
        'subject' => 'New quotation v:version for :project',
        'intro' => 'We have sent quotation version :version for the project ":project".',
        'total' => 'Quotation total: :total',
        'valid_until' => 'The quotation is valid until :date.',
        'no_limit' => 'This quotation has no expiry date.',
        'action' => 'View quotation',
        'outro' => 'In the portal you can accept, decline, or negotiate through chat.',
    ],

    'quotation_expiring' => [
        'subject' => 'Quotation v:version for :project is about to expire',
        'intro' => 'Quotation version :version for the project ":project" is valid until :date (:when).',
        'days' => '{0} expires today|{1} 1 day left|[2,*] :count days left',
        'action' => 'View quotation',
        'outro' => 'If you need more time or changes, write to us in chat before it expires.',
    ],

    'dp_invoice' => [
        'subject' => 'Down payment invoice :number for :project',
        'intro' => 'Thank you for accepting the quotation for ":project". Your down payment invoice has been issued.',
        'number' => 'Invoice number: :number',
        'amount' => 'Amount: :amount',
        'due' => 'Due date: :date',
        'no_due' => 'No due date.',
        'action' => 'View invoice',
        'outro' => 'Work starts once we have verified your down payment.',
    ],

    'chat_digest' => [
        'subject_admin' => 'New message from the client on :project',
        'subject_client' => 'New message from the Quix Code team on :project',
        'intro' => '{1} You have 1 unread message on the project ":project".|[2,*] You have :count unread messages on the project ":project".',
        'hint' => 'The message text is not included in this email. Open the chat to read it.',
        'action' => 'Open chat',
    ],
];
