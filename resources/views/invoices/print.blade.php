<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('projects.invoice.document_title', ['number' => $invoice->invoice_number]) }}</title>
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
        .total { display: flex; justify-content: flex-end; margin-top: 20px; font-size: 20px; font-weight: 700; }
        .status { display: inline-block; margin-top: 18px; border: 1px solid #dce4df; border-radius: 999px; padding: 5px 10px; font-size: 13px; }
        footer { margin-top: 48px; border-top: 1px solid #dce4df; padding-top: 16px; font-size: 12px; }
        @media (max-width: 640px) { .sheet { margin: 0; padding: 24px; } header { flex-direction: column; } .meta { text-align: left; } }
        @media print { body { background: white; } .toolbar { display: none; } .sheet { max-width: none; margin: 0; padding: 0; box-shadow: none; } @page { size: A4; margin: 18mm; } }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="{{ __('projects.invoice.print_actions') }}">
        <a href="{{ $backUrl }}">{{ __('ui.back') }}</a>
        <button type="button" onclick="window.print()">{{ __('projects.invoice.save_pdf') }}</button>
    </nav>
    <main class="sheet">
        <header>
            <div>
                <p>{{ config('app.name') }}</p>
                <h1>{{ __('projects.invoice.document_heading') }}</h1>
            </div>
            <div class="meta">
                <strong>{{ $invoice->invoice_number }}</strong>
                <p>{{ __('projects.invoice.issued_at') }}: {{ ($invoice->issued_at ?? $invoice->created_at)->translatedFormat('d M Y') }}</p>
                @if ($invoice->due_date)
                    <p>{{ __('projects.invoice.due_date') }}: {{ $invoice->due_date->translatedFormat('d M Y') }}</p>
                @endif
            </div>
        </header>
        <section class="client">
            <strong>{{ __('projects.invoice.bill_to') }}</strong>
            <p>{{ $invoice->project->client?->name }}</p>
            @if ($invoice->project->client?->email)<p>{{ $invoice->project->client->email }}</p>@endif
            <p>{{ __('projects.invoice.project') }}: {{ $invoice->project->title }}</p>
        </section>
        <table>
            <thead><tr>
                <th>{{ __('projects.invoice.description') }}</th>
                <th class="numeric">{{ __('projects.invoice.quantity') }}</th>
                <th class="numeric">{{ __('projects.invoice.unit_price') }}</th>
                <th class="numeric">{{ __('projects.invoice.amount') }}</th>
            </tr></thead>
            <tbody>
                @foreach ($invoice->items as $item)
                    <tr>
                        <td>{{ $item->title }}</td>
                        <td class="numeric">{{ rtrim(rtrim(number_format((float) $item->qty, 3, '.', ''), '0'), '.') }}</td>
                        <td class="numeric">{{ \App\Support\Money::of($item->unit_price, $invoice->currency)->format() }}</td>
                        <td class="numeric">{{ \App\Support\Money::of($item->amount, $invoice->currency)->format() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="total"><span>{{ __('projects.invoice.total') }}: &nbsp;</span><span>{{ $invoice->money()->format() }}</span></div>
        <span class="status">{{ $invoice->status->label() }}</span>
        <footer>{{ __('projects.invoice.document_footer') }}</footer>
    </main>
</body>
</html>
