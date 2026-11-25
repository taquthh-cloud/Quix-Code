<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('projects.invoice.document_title', ['number' => $invoice->invoice_number], 'id') }} / {{ __('projects.invoice.document_title', ['number' => $invoice->invoice_number], 'en') }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; color: #14251f; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7f5; }
        .toolbar { display: flex; justify-content: flex-end; gap: 12px; max-width: 860px; margin: 24px auto 12px; }
        .toolbar a, .toolbar button { border: 0; border-radius: 8px; padding: 10px 14px; background: #145c49; color: white; font: inherit; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 860px; margin: 0 auto 40px; padding: 56px; background: white; box-shadow: 0 12px 40px #17382a14; }
        header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #145c49; padding-bottom: 24px; }
        h1 { margin: 0; font-size: 30px; letter-spacing: .04em; }
        p { margin: 6px 0; color: #53635c; }
        .meta { text-align: right; }
        .client { margin: 32px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #dce4df; text-align: left; }
        th { color: #53635c; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
        .numeric { text-align: right; font-variant-numeric: tabular-nums; }
        .summary { margin: 20px 0 0 auto; width: 100%; max-width: 360px; border-collapse: collapse; }
        .summary td { padding: 6px 10px; border-bottom: 0; }
        .summary tr.grand td { border-top: 2px solid #145c49; font-size: 20px; font-weight: 700; padding-top: 12px; }
        .total { display: flex; justify-content: flex-end; margin-top: 20px; font-size: 20px; font-weight: 700; }
        .status { display: inline-block; margin-top: 18px; border: 1px solid #dce4df; border-radius: 999px; padding: 5px 10px; font-size: 13px; }
        footer { margin-top: 48px; border-top: 1px solid #dce4df; padding-top: 16px; font-size: 12px; }
        @media (max-width: 640px) { .sheet { margin: 0; padding: 24px; } header { flex-direction: column; } .meta { text-align: left; } }
        @media print { body { background: white; } .toolbar { display: none; } .sheet { max-width: none; margin: 0; padding: 0; box-shadow: none; } @page { size: A4; margin: 18mm; } }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="{{ __('projects.invoice.print_actions', [], 'id') }} / {{ __('projects.invoice.print_actions', [], 'en') }}">
        <a href="{{ $backUrl }}">{{ __('ui.back', [], 'id') }} / {{ __('ui.back', [], 'en') }}</a>
        <button type="button" onclick="window.print()">{{ __('projects.invoice.save_pdf', [], 'id') }} / {{ __('projects.invoice.save_pdf', [], 'en') }}</button>
    </nav>
    <main class="sheet">
        <header>
            <div>
                <p>{{ $company['name'] }}</p>
                @if ($company['address'])<p>{{ $company['address'] }}</p>@endif
                @if ($company['npwp'])<p>{{ __('projects.invoice.company_npwp', [], 'id') }} / {{ __('projects.invoice.company_npwp', [], 'en') }}: {{ $company['npwp'] }}</p>@endif
                <h1>{{ __('projects.invoice.document_heading') }}</h1>
            </div>
            <div class="meta">
                <strong>{{ $invoice->invoice_number }}</strong>
                <p>{{ __('projects.invoice.issued_at', [], 'id') }} / {{ __('projects.invoice.issued_at', [], 'en') }}: {{ ($invoice->issued_at ?? $invoice->created_at)->format('d/m/Y') }}</p>
                @if ($invoice->due_date)
                    <p>{{ __('projects.invoice.due_date', [], 'id') }} / {{ __('projects.invoice.due_date', [], 'en') }}: {{ $invoice->due_date->format('d/m/Y') }}</p>
                @endif
            </div>
        </header>
        <section class="client">
            <strong>{{ __('projects.invoice.bill_to', [], 'id') }} / {{ __('projects.invoice.bill_to', [], 'en') }}</strong>
            <p>{{ $invoice->project->client?->name }}</p>
            @if ($invoice->project->client?->email)<p>{{ $invoice->project->client->email }}</p>@endif
            <p>{{ __('projects.invoice.project', [], 'id') }} / {{ __('projects.invoice.project', [], 'en') }}: {{ $invoice->project->title }}</p>
        </section>
        <table>
            <thead><tr>
                <th>{{ __('projects.invoice.description', [], 'id') }} / {{ __('projects.invoice.description', [], 'en') }}</th>
                <th class="numeric">{{ __('projects.invoice.quantity', [], 'id') }} / {{ __('projects.invoice.quantity', [], 'en') }}</th>
                <th class="numeric">{{ __('projects.invoice.unit_price', [], 'id') }} / {{ __('projects.invoice.unit_price', [], 'en') }}</th>
                <th class="numeric">{{ __('projects.invoice.amount', [], 'id') }} / {{ __('projects.invoice.amount', [], 'en') }}</th>
            </tr></thead>
            <tbody>
                @forelse ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->title }}</td>
                        <td class="numeric">{{ rtrim(rtrim(number_format((float) $item->qty, 3, '.', ''), '0'), '.') }}</td>
                        <td class="numeric">{{ \App\Support\Money::of($item->unit_price, $invoice->currency)->format() }}</td>
                        <td class="numeric">{{ \App\Support\Money::of($item->amount, $invoice->currency)->format() }}</td>
                    </tr>
                @empty
                    {{-- Invoice DP/termin otomatis tidak punya baris item: tampilkan satu baris porsi termin. --}}
                    <tr>
                        <td colspan="3">
                            {{ __('enums.invoice_type.'.$invoice->type->value, [], 'id') }} / {{ __('enums.invoice_type.'.$invoice->type->value, [], 'en') }}
                            @if ($invoice->term_percent_bp)
                                · {{ __('projects.invoice.term_share', ['percent' => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($invoice->term_percent_bp), '0'), '.')], 'id') }} / {{ __('projects.invoice.term_share', ['percent' => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($invoice->term_percent_bp), '0'), '.')], 'en') }}
                            @endif
                        </td>
                        <td class="numeric">{{ \App\Support\Money::of((int) $invoice->subtotal, $invoice->currency)->format() }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <table class="summary">
            @if ($invoice->discount_amount > 0 || $invoice->tax_amount > 0)
                <tr>
                    <td>{{ __('projects.invoice.subtotal', [], 'id') }} / {{ __('projects.invoice.subtotal', [], 'en') }}</td>
                    <td class="numeric">{{ \App\Support\Money::of((int) $invoice->subtotal, $invoice->currency)->format() }}</td>
                </tr>
            @endif
            @if ($invoice->discount_amount > 0)
                <tr>
                    <td>{{ __('projects.invoice.discount', [], 'id') }} / {{ __('projects.invoice.discount', [], 'en') }}</td>
                    <td class="numeric">-{{ \App\Support\Money::of((int) $invoice->discount_amount, $invoice->currency)->format() }}</td>
                </tr>
            @endif
            @if ($invoice->tax_amount > 0)
                <tr>
                    <td>{{ __('projects.invoice.tax', [], 'id') }} / {{ __('projects.invoice.tax', [], 'en') }}</td>
                    <td class="numeric">{{ \App\Support\Money::of((int) $invoice->tax_amount, $invoice->currency)->format() }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td>{{ __('projects.invoice.total', [], 'id') }} / {{ __('projects.invoice.total', [], 'en') }}</td>
                <td class="numeric">{{ $invoice->money()->format() }}</td>
            </tr>
        </table>
        <span class="status">{{ __('enums.invoice_status.'.$invoice->status->value, [], 'id') }} / {{ __('enums.invoice_status.'.$invoice->status->value, [], 'en') }}</span>
        @if ($bank['name'] || $bank['account_name'] || $bank['account_number'])
            <section class="client">
                <strong>{{ __('projects.invoice.bank_details', [], 'id') }} / {{ __('projects.invoice.bank_details', [], 'en') }}</strong>
                @if ($bank['name'])<p>{{ __('projects.invoice.bank_name', [], 'id') }} / {{ __('projects.invoice.bank_name', [], 'en') }}: {{ $bank['name'] }}</p>@endif
                @if ($bank['account_name'])<p>{{ __('projects.invoice.account_name', [], 'id') }} / {{ __('projects.invoice.account_name', [], 'en') }}: {{ $bank['account_name'] }}</p>@endif
                @if ($bank['account_number'])<p>{{ __('projects.invoice.account_number', [], 'id') }} / {{ __('projects.invoice.account_number', [], 'en') }}: {{ $bank['account_number'] }}</p>@endif
            </section>
        @endif
        <footer>{{ $footers['id'] }} / {{ $footers['en'] }}</footer>
    </main>
</body>
</html>
