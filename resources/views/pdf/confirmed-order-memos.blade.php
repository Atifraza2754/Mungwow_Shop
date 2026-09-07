<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 5mm; }

        * { box-sizing: border-box; }

        body {
            font-family: "Noto Nastaliq Urdu", "Jameel Noori Nastaleeq", "Urdu Typesetting", "DejaVu Sans", sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            line-height: 1.1;
        }

        table.grid { width: 100%; border-collapse: collapse; }
        table.grid td.cell {
            width: 50%;
            vertical-align: top;
            padding: 0.5mm;     /* smaller gap between memos */
        }

        /* ---- Single memo (its own border, hugs its content) ---- */
        .memo {
            border: 1px solid #000;
            padding: 1.5mm;
        }

        .memo .title {
            text-align: center;
            font-weight: bold;
            font-size: 12px;
            text-decoration: underline;
            margin-bottom: 1mm;
        }

        .memo .hdr { width: 100%; margin-bottom: 1mm; border-collapse: collapse; }
        .memo .hdr td { vertical-align: top; }
        .memo .hdr td.h-cat  { width: 62%; padding-right: 2mm; }
        .memo .hdr td.h-date { width: 38%; }
        .memo .field {
            border-bottom: 1px solid #000;
            padding: 0.35mm 0;
            margin-top: 1px;
            font-size: 11px;
            unicode-bidi: plaintext;
        }
        .memo .field .lbl { font-weight: bold; }

        /* ---- Items table ---- */
        table.items {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        table.items th,
        table.items td {
            border: 1px solid #000;
            padding: 1px 2px;
            font-size: 11px;
            vertical-align: top;
        }
        table.items th { font-weight: bold; text-align: left; }

        .c-sno   { width: 10%; }
        .c-desc  { width: 54%; unicode-bidi: plaintext; }
        .c-qty   { width: 16%; white-space: nowrap; }
        .c-price { width: 20%; text-align: right; white-space: nowrap; }

        td.c-desc.sub { padding-left: 12px; }
        tr.filler td { height: 6px; }

        /* ---- Total: its own bordered box BELOW the items table ---- */
        table.total-box {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            border-top: 0;            /* sits flush under the items table */
        }
        table.total-box td {
            border: 1px solid #000;
            padding: 1px 2px;
            font-size: 11px;
            vertical-align: top;
        }
        table.total-box td.t-label { width: 80%; text-align: right; font-weight: bold; }
        table.total-box td.t-val   { width: 20%; text-align: right; }

        .memo .words { margin-top: 1.5mm; font-weight: bold; font-size: 11px; }
        .memo .words .ln {
            display: inline-block;
            border-bottom: 1px solid #000;
            width: 55%;
        }
    </style>
</head>
<body>
@php
    // 2 memos per row; rows flow down the page and break across pages
    // naturally (memos are per-category and can be any height).
    $rows = array_chunk($memos, 2);
@endphp

<table class="grid">
    @foreach ($rows as $row)
        <tr>
            @for ($c = 0; $c < 2; $c++)
                <td class="cell">
                    @isset($row[$c])
                        @include('pdf.partials.memo', ['memo' => $row[$c]])
                    @endisset
                </td>
            @endfor
        </tr>
    @endforeach
</table>
</body>
</html>
