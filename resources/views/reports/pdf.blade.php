<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }} · {{ $family }}</title>
    <style>
        /* PDF only: dompdf cannot read the app's CSS variables, so the theme colours are repeated here. */
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; }
        header { border-bottom: 2px solid #2563eb; padding-bottom: 8px; margin-bottom: 14px; }
        h1 { font-size: 16px; margin: 0; color: #2563eb; }
        .muted { color: #64748b; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
        .cards td { border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px; width: 33%; }
        .cards .value { font-size: 13px; font-weight: bold; }
        .income { color: #16a34a; } .expense { color: #dc2626; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f5f8ff; text-align: left; font-weight: bold; border-bottom: 1px solid #e2e8f0; padding: 5px 4px; }
        table.data td { border-bottom: 1px solid #e2e8f0; padding: 4px; }
        table.data td.num, table.data th.num { text-align: right; }
        footer { position: fixed; bottom: -14px; left: 0; right: 0; font-size: 8px; color: #64748b; }
    </style>
</head>
<body>
    <header>
        <h1>{{ $report['title'] }}</h1>
        <div>{{ $family }} · <span class="muted">{{ $report['tab'] === 'yearly' ? 'Tahun '.$report['year'] : $report['rangeLabel'] }}</span></div>
    </header>

    <table class="cards">
        <tr>
            <td><div class="muted">Pemasukan</div><div class="value income">{{ $report['totals']['income']->formatted }}</div></td>
            <td><div class="muted">Pengeluaran</div><div class="value expense">{{ $report['totals']['expense']->formatted }}</div></td>
            <td><div class="muted">Selisih</div><div class="value">{{ $report['totals']['net']->formatted }}</div></td>
        </tr>
    </table>

    @if (count($table['rows']) === 0)
        <p class="muted">Tidak ada data pada rentang ini.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    @foreach ($table['headings'] as $index => $heading)
                        <th class="{{ $index > 0 ? 'num' : '' }}">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($table['rows'] as $row)
                    <tr>
                        @foreach ($row as $index => $cell)
                            <td class="{{ $index > 0 && is_numeric($cell) ? 'num' : '' }}">
                                @if (is_int($cell) && $index > 0 && ! in_array($table['headings'][$index] ?? '', ['Transaksi'], true))
                                    {{ \App\Support\Money\MoneyFormatter::format($cell) }}
                                @elseif (is_float($cell))
                                    {{ number_format($cell, 1, ',', '.') }}%
                                @else
                                    {{ $cell ?? '–' }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <footer>Dibuat {{ $generatedAt }} · Budgeting App</footer>
</body>
</html>
