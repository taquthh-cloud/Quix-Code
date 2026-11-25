<?php

return [
    'user_role' => ['admin' => 'Super Admin', 'staff' => 'Staff', 'client' => 'Client'],
    'project_status' => [
        'lead' => 'Lead', 'quoted' => 'Quoted', 'negotiating' => 'Negotiating',
        'deal' => 'Deal', 'awaiting_dp' => 'Awaiting Down Payment', 'active' => 'In Progress', 'on_hold' => 'On Hold',
        'awaiting_final' => 'Awaiting Final Payment', 'handover' => 'Handover',
        'warranty' => 'Warranty', 'closed' => 'Closed', 'cancelled' => 'Cancelled',
    ],
    'invoice_type' => ['dp' => 'Down Payment', 'termin' => 'Installment', 'final' => 'Final Payment'],
    'invoice_status' => [
        'draft' => 'Draft', 'sent' => 'Sent', 'unpaid' => 'Unpaid', 'awaiting_verification' => 'Awaiting Verification',
        'paid' => 'Paid', 'cancelled' => 'Cancelled', 'void' => 'Void',
    ],
    'ticket_status' => ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'closed' => 'Closed'],
    'currency' => ['IDR' => 'Indonesian Rupiah (IDR)', 'USD' => 'US Dollar (USD)'],
    'quotation_status' => ['draft' => 'Draft', 'sent' => 'Sent', 'accepted' => 'Accepted', 'rejected' => 'Rejected', 'expired' => 'Expired', 'superseded' => 'Superseded'],
    'payment_status' => ['pending' => 'Pending Verification', 'verified' => 'Verified', 'rejected' => 'Rejected'],
    'milestone_status' => ['pending' => 'Not Started', 'in_progress' => 'In Progress', 'done' => 'Done'],
    'ticket_category' => ['revision' => 'Revision', 'bug_report' => 'Bug Report'],
    'ticket_priority' => ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'],
    'handover_method' => ['download' => 'Direct Download', 'discord_email' => 'Send via Discord/Email', 'managed_hosting' => 'Managed Hosting'],
    'file_visibility' => ['private' => 'Private', 'client' => 'Client', 'public' => 'Public'],
];
