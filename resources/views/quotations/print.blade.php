@php
    // Dokumen dua bahasa (ID / EN), seperti invoice.
    $t = fn (string $key, array $replace = []) => __($key, $replace, 'id').' / '.__($key, $replace, 'en');
    $money = fn (int $amount) => \App\Support\Money::of($amount, $quotation->currency)->format();
    $percent = fn (int $bp) => rtrim(rtrim(\App\Support\QuotationCalculator::bpToPercent($bp), '0'), '.') ?: '0';
    $overhead = $quotation->operational_cost + $quotation->marginMoney()->amount;
    $reference = 'QC-'.strtoupper(substr((string) $quotation->id, -8)).'-v'.$quotation->version;
    $termBps = $quotation->termBps();
    $termMoney = $quotation->termMoney();
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $t('quotations.print.document_title', ['reference' => $reference]) }}</title>
    <style>
        :root { color-scheme: light; font-family: Arial, Helvetica, sans-serif; color: #14251f; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f7f5; }
        .toolbar { display: flex; justify-content: flex-end; gap: 12px; max-width: 860px; margin: 24px auto 12px; }
        .toolbar a, .toolbar button { border: 0; border-radius: 8px; padding: 10px 14px; background: #145c49; color: white; font: inherit; text-decoration: none; cursor: pointer; }
        .sheet { max-width: 860px; margin: 0 auto 40px; padding: 56px; background: white; box-shadow: 0 12px 40px #17382a14; }
        header { display: flex; justify-content: space-between; gap: 24px; border-bottom: 2px solid #145c49; padding-bottom: 24px; }
        h1 { margin: 0; font-size: 30px; letter-spacing: .04em; }
        h2 { margin: 0 0 8px; font-size: 14px; text-transform: uppercase; letter-spacing: .06em; color: #145c49; }
        p { margin: 6px 0; color: #53635c; }
        .meta { text-align: right; }
        .client { margin: 32px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #dce4df; text-align: left; vertical-align: top; }
        th { color: #53635c; font-size: 12px; text-transform: uppercase; letter-spacing: .06em; }
        td .desc { display: block; margin-top: 4px; font-size: 12px; color: #53635c; white-space: pre-line; }
        .numeric { text-align: right; font-variant-numeric: tabular-nums; }
        .summary { margin-left: auto; margin-top: 20px; width: 100%; max-width: 380px; }
        .summary td { border-bottom: 0; padding: 4px 10px; }
        .summary .grand td { border-top: 2px solid #145c49; padding-top: 10px; font-size: 18px; font-weight: 700; }
        .status { display: inline-block; margin-top: 18px; border: 1px solid #dce4df; border-radius: 999px; padding: 5px 10px; font-size: 13px; }
        .status.expired { border-color: #b4233c; color: #b4233c; }
        .terms { margin-top: 32px; white-space: pre-line; font-size: 12px; color: #33433c; }
        footer { margin-top: 48px; border-top: 1px solid #dce4df; padding-top: 16px; font-size: 12px; }
        @media (max-width: 640px) { .sheet { margin: 0; padding: 24px; } header { flex-direction: column; } .meta { text-align: left; } }
        @media print { body { background: white; } .toolbar { display: none; } .sheet { max-width: none; margin: 0; padding: 0; box-shadow: none; } @page { size: A4; margin: 18mm; } }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="{{ $t('quotations.print.actions') }}">
        <a href="{{ $backUrl }}">{{ $t('ui.back') }}</a>
        <button type="button" onclick="window.print()">{{ $t('quotations.print.save_pdf') }}</button>
    </nav>
    <main class="sheet">
        <header>
            <div>
                <p>{{ $company['name'] }}</p>
                @if ($company['address'])<p>{{ $company['address'] }}</p>@endif
                @if ($company['npwp'])<p>{{ $t('projects.invoice.company_npwp') }}: {{ $company['npwp'] }}</p>@endif
                <h1>{{ $t('quotations.print.heading') }}</h1>
            </div>
            <div class="meta">
                <strong>{{ $reference }}</strong>
                <p>{{ $t('quotations.version') }}: {{ $quotation->version }}</p>
                @if ($quotation->sent_at)
                    <p>{{ $t('quotations.print.issued_at') }}: {{ $quotation->sent_at->format('d/m/Y') }}</p>
                @endif
                @if ($quotation->valid_until)
                    <p>{{ $t('quotations.valid_until') }}: {{ $quotation->valid_until->format('d/m/Y') }}</p>
                @endif
            </div>
        </header>

        <section class="client">
            <h2>{{ $t('quotations.print.prepared_for') }}</h2>
            <p>{{ $quotation->project->client?->name }}</p>
            @if ($quotation->project->client?->email)<p>{{ $quotation->project->client->email }}</p>@endif
            <p>{{ $t('projects.invoice.project') }}: {{ $quotation->project->title }}</p>
        </section>

        <table>
            <thead><tr>
                <th>{{ $t('quotations.print.work') }}</th>
                <th class="numeric">{{ $t('quotations.print.hours') }}</th>
                <th class="numeric">{{ $t('quotations.print.rate') }}</th>
                <th class="numeric">{{ $t('projects.invoice.amount') }}</th>
            </tr></thead>
            <tbody>
                @foreach ($quotation->items as $item)
                    <tr>
                        <td>{{ $item->title }}@if ($item->description)<span class="desc">{{ $item->description }}</span>@endif</td>
                        <td class="numeric">{{ rtrim(rtrim((string) $item->hours, '0'), '.') ?: '0' }}</td>
                        <td class="numeric">{{ $money($item->rate) }}</td>
                        <td class="numeric">{{ $money($item->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="summary">
            <tbody>
                <tr><td>{{ $t('quotations.subtotal') }}</td><td class="numeric">{{ $money($quotation->subtotal) }}</td></tr>
                @if ($overhead > 0)
                    <tr><td>{{ $t('quotations.overhead') }}</td><td class="numeric">{{ $money($overhead) }}</td></tr>
                @endif
                @if ($quotation->discount_amount > 0)
                    <tr><td>{{ $t('quotations.builder.discount') }}</td><td class="numeric">&minus; {{ $money($quotation->discount_amount) }}</td></tr>
                @endif
                @if ($quotation->tax_bp > 0)
                    <tr><td>{{ $t('quotations.tax', ['rate' => $quotation->taxPercentLabel()]) }}</td><td class="numeric">{{ $money($quotation->tax_amount) }}</td></tr>
                @endif
                <tr class="grand"><td>{{ $t('quotations.total') }}</td><td class="numeric">{{ $money($quotation->total) }}</td></tr>
            </tbody>
        </table>

        <section class="client">
            <h2>{{ $t('quotations.print.payment_scheme') }}</h2>
            <table>
                <tbody>
                    @foreach ($termMoney as $i => $term)
                        <tr>
                            <td>{{ $i === 0
                                ? $t('quotations.dp_due', ['percent' => $percent($termBps[0])])
                                : $t('quotations.term_n', ['n' => $i + 1, 'percent' => $percent($termBps[$i])]) }}</td>
                            <td class="numeric">{{ $term->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <span class="status @if ($quotation->isExpired()) expired @endif">
            {{ __('enums.quotation_status.'.$quotation->status->value, [], 'id') }} / {{ __('enums.quotation_status.'.$quotation->status->value, [], 'en') }}
            @if ($quotation->status === \App\Enums\QuotationStatus::Sent && $quotation->isExpired())
                · {{ $t('quotations.expired') }}
            @endif
        </span>

        @if ($terms['id'] !== '' || $terms['en'] !== '')
            <section class="client">
                <h2>{{ $t('quotations.print.terms') }}@if ($quotation->terms_version) ({{ $quotation->terms_version }})@endif</h2>
                @if ($terms['id'] !== '')<div class="terms">{{ $terms['id'] }}</div>@endif
                @if ($terms['en'] !== '')<div class="terms">{{ $terms['en'] }}</div>@endif
            </section>
        @elseif ($termsChanged)
            <section class="client">
                <h2>{{ $t('quotations.print.terms') }} ({{ $quotation->terms_version }})</h2>
                <p>{{ $t('quotations.print.terms_changed') }}</p>
            </section>
        @endif

        <footer>{{ $company['name'] }} · {{ $reference }}</footer>
    </main>
</body>
</html>
