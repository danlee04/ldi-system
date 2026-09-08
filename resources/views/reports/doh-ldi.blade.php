@php
    $doh = config('ldi.doh');
    $monthName = $month === null
        ? 'ALL MONTHS'
        : strtoupper(now()->startOfYear()->addMonths($month - 1)->format('F'));
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>DOH LDI training report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        body { font-family: "Arial Narrow", Arial, sans-serif; font-size: 8.5px; line-height: 1.3; color: #000; background: #fff; }

        .page { padding: 15px; }

        .header-title { text-align: center; margin-bottom: 10px; }
        .header-title h1 { font-size: 13px; text-transform: uppercase; }
        .meta { font-weight: bold; margin-bottom: 8px; font-size: 9px; }

        table { width: 100%; border-collapse: collapse; table-layout: fixed; border: 0.5pt solid #000; }

        th {
            word-break: break-all; background-color: #FFCC00; overflow: hidden;
            border: 0.5pt solid #000; font-size: 8px; padding: 2px;
            vertical-align: middle; text-align: center;
        }

        td { border: 0.5pt solid #000; padding: 4px 2px; font-size: 8px; word-wrap: break-word; vertical-align: middle; }

        .tc { text-align: center; }
        .tr { text-align: right; }
        .nb { border: none !important; }

        .count { font-weight: bold; text-align: center; }
        .totals-row td { font-weight: bold; background-color: #fff9cc; }

        .sig-table { width: 100%; margin-top: 30px; }
        .sig-name { font-weight: bold; text-decoration: underline; text-align: center; font-size: 10px; }
        .sig-title { text-align: center; font-size: 8px; }

        .note { margin-top: 10px; font-size: 8px; font-style: italic; }

        .toolbar { margin-bottom: 12px; }
        .toolbar a, .toolbar button {
            font: inherit; font-size: 11px; padding: 4px 10px; margin-right: 6px;
            border: 1px solid #999; background: #f4f4f4; cursor: pointer; text-decoration: none; color: #000;
        }

        @page { size: A4 landscape; margin: 0.5cm; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
<div class="page">
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ route('reports') }}">Back to reports</a>
    </div>

    <div class="header-title">
        <h3>DEPARTMENT OF HEALTH</h3>
        <h1>Training Report on Attendance to Learning and Development Interventions</h1>
    </div>

    <div class="meta">
        <div>NAME OF OFFICE/BUREAU/HOSPITAL: <u>{{ $doh['office'] }}</u></div>
        <div>MONTH: {{ $monthName }}</div>
        <div>YEAR: {{ $year }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="3" style="width:7%">PROPONENT DIVISION/<br>PROGRAM</th>
                <th rowspan="3" style="width:8%">REGION/<br>HOSPITAL/<br>BUREAU</th>
                <th rowspan="3" style="width:5%">T/W/O</th>
                <th rowspan="3" style="width:10%">T/W/O<br>FINANCED BY<br>DEV'T PARTNERS</th>
                <th rowspan="3" style="width:13%">TITLE</th>
                <th rowspan="3" style="width:7%">DATE OF ACTIVITY</th>
                <th rowspan="3" style="width:5%">NO. OF DAYS</th>
                <th rowspan="3" style="width:5%">NO. OF HOURS</th>
                <th rowspan="3" style="width:5%">BUDGET</th>
                <th colspan="21" style="background-color:#2f9cfc">NO. OF PARTICIPANTS PER CATEGORY</th>
                <th colspan="2" rowspan="2" style="background-color:#7fc3ff">GENDER</th>
            </tr>
            <tr>
                <th colspan="4" style="background-color:#fffeba">DOH-CO</th>
                <th colspan="4">DOH-RO</th>
                <th colspan="5" style="background-color:#b3fdc1">LGU / RHU</th>
                <th colspan="3" style="background-color:#01af8f">DOH-HOSP</th>
                <th colspan="3" style="background-color:#4dfe29">TRCs</th>
                <th colspan="2" style="background-color:#38a2ff">Development Partners</th>
            </tr>
            <tr>
                <th style="background-color:#fffeba">EXECOM</th>
                <th style="background-color:#fffeba">DIR</th>
                <th style="background-color:#fffeba">SG 15+</th>
                <th style="background-color:#fffeba">SG 15-</th>
                <th>DIR</th><th>ARD</th><th>SG 15+</th><th>SG 15-</th>
                <th style="background-color:#b3fdc1">PHO/CHO</th>
                <th style="background-color:#b3fdc1">MD/MH</th>
                <th style="background-color:#b3fdc1">RN</th>
                <th style="background-color:#b3fdc1">MW</th>
                <th style="background-color:#b3fdc1">Others</th>
                <th style="background-color:#01af8f">MCC</th>
                <th style="background-color:#01af8f">DM/TS</th>
                <th style="background-color:#01af8f">Ad. Staff</th>
                <th style="background-color:#4dfe29">MCC</th>
                <th style="background-color:#4dfe29">DM</th>
                <th style="background-color:#4dfe29">Ad. Staff</th>
                <th style="background-color:#38a2ff">Resource Person/s</th>
                <th style="background-color:#38a2ff">Participants</th>
                <th style="background-color:#fc9c2f">F</th>
                <th style="background-color:#2f9cfc">M</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($rows as $row)
                @php($plan = $row['plan'])
                <tr>
                    <td></td>
                    <td>{{ $doh['office'] }}</td>
                    <td class="tc">{{ strtoupper($plan->type_of_training ?? '') }}</td>
                    <td>{{ $plan->development_partner }}</td>
                    <td>{{ $plan->title }}</td>
                    <td class="tc" style="white-space:nowrap">{{ $plan->inclusive_dates }}</td>
                    <td class="tc">{{ $row['days'] }}</td>
                    <td class="tc">{{ $plan->hours }}</td>
                    <td class="tr">{{ $plan->budget === null ? '—' : number_format((float) $plan->budget, 2) }}</td>

                    {{-- DOH-CO, DOH-RO, LGU/RHU, DOH-HOSP: this agency sends nobody in those categories. --}}
                    @for ($i = 0; $i < 16; $i++)
                        <td></td>
                    @endfor

                    <td class="count">{{ $row['mcc'] ?: '' }}</td>
                    <td class="count">{{ $row['dm'] ?: '' }}</td>
                    <td class="count">{{ $row['ad_staff'] ?: '' }}</td>

                    <td></td>
                    <td></td>

                    <td class="count">{{ $row['female'] ?: '' }}</td>
                    <td class="count">{{ $row['male'] ?: '' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="32" class="tc" style="padding:10px;color:#666;">
                        No planned training fell in this period.
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if ($rows !== [])
            <tfoot>
                <tr class="totals-row">
                    <td colspan="7" class="tr" style="padding-right:4px">TOTAL</td>
                    <td class="tc">{{ $totals['hours'] }}</td>
                    <td class="tr">{{ number_format($totals['budget'], 2) }}</td>

                    @for ($i = 0; $i < 16; $i++)
                        <td></td>
                    @endfor

                    <td class="count">{{ $totals['mcc'] ?: '' }}</td>
                    <td class="count">{{ $totals['dm'] ?: '' }}</td>
                    <td class="count">{{ $totals['ad_staff'] ?: '' }}</td>

                    <td></td>
                    <td></td>

                    <td class="count">{{ $totals['female'] ?: '' }}</td>
                    <td class="count">{{ $totals['male'] ?: '' }}</td>
                </tr>
            </tfoot>
        @endif
    </table>

    @if ($totals['uncategorised'] > 0 || $totals['unstated'] > 0)
        <div class="note">
            @if ($totals['uncategorised'] > 0)
                {{ $totals['uncategorised'] }} attendance(s) fall outside the three TRC categories and are counted only under gender.
            @endif
            @if ($totals['unstated'] > 0)
                {{ $totals['unstated'] }} attendance(s) have no sex on record.
            @endif
        </div>
    @endif

    <table class="sig-table nb">
        <tr class="nb">
            @foreach ($doh['signatories'] as $signatory)
                <td class="nb" style="width:25%">{{ $signatory['role'] }}</td>
            @endforeach
        </tr>
        <tr class="nb">
            <td colspan="{{ count($doh['signatories']) }}" class="nb" style="height:40px;"></td>
        </tr>
        <tr class="nb">
            @foreach ($doh['signatories'] as $signatory)
                <td class="nb sig-name">{{ $signatory['name'] }}</td>
            @endforeach
        </tr>
        <tr class="nb">
            @foreach ($doh['signatories'] as $signatory)
                <td class="nb sig-title">{{ $signatory['title'] }}</td>
            @endforeach
        </tr>
    </table>
</div>
</body>
</html>
