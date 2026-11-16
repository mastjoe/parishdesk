{{--
    A letter on the parish letterhead, for the PDFs the parish sends out.

    Expects $parish (ParishSetting), $logo (data URI or null) and
    $parishPriest (name or null). Pages fill the "title" and "body" sections.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        @page { margin: 22mm 20mm 20mm; }
        body { font-family: "DejaVu Serif", serif; font-size: 11pt; line-height: 1.5; color: #1f2933; }
        .letterhead { width: 100%; border-bottom: 2px solid #1f2933; padding-bottom: 10px; margin-bottom: 18px; }
        .letterhead td { vertical-align: middle; }
        .letterhead .logo { width: 70px; }
        .letterhead .logo img { width: 64px; height: auto; }
        .letterhead .parish { text-align: center; }
        .letterhead .name { font-size: 16pt; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; }
        .letterhead .diocese { font-size: 10pt; font-style: italic; }
        .letterhead .contact { font-size: 9pt; color: #52606d; }
        .meta { width: 100%; font-size: 10pt; margin-bottom: 14px; }
        .meta .right { text-align: right; }
        h1 { font-size: 13pt; text-align: center; text-transform: uppercase; letter-spacing: 1px; margin: 6px 0 16px; }
        p { margin: 0 0 10px; text-align: justify; }
        table.details { width: 100%; border-collapse: collapse; margin: 6px 0 14px; }
        table.details th { width: 28%; text-align: left; font-weight: bold; vertical-align: top; padding: 4px 8px 4px 0; }
        table.details td { vertical-align: top; padding: 4px 0; }
        .signature { margin-top: 42px; width: 100%; }
        .signature td { vertical-align: bottom; }
        .signature .line { border-top: 1px solid #1f2933; width: 220px; padding-top: 4px; }
        .signature .seal { text-align: right; font-size: 9pt; color: #7b8794; }
    </style>
</head>
<body>
    <table class="letterhead">
        <tr>
            <td class="logo">
                @if ($logo)
                    <img src="{{ $logo }}" alt="">
                @endif
            </td>
            <td class="parish">
                <div class="name">{{ $parish->name }}</div>
                @if ($parish->diocese)
                    <div class="diocese">{{ $parish->deanery ? "{$parish->deanery} Deanery, " : '' }}{{ $parish->diocese }}</div>
                @endif
                <div class="contact">
                    {{ collect([$parish->address, $parish->city, $parish->state, $parish->country])->filter()->implode(', ') }}
                </div>
                <div class="contact">
                    {{ collect([$parish->phone, $parish->email, $parish->website])->filter()->implode(' · ') }}
                </div>
            </td>
            <td class="logo"></td>
        </tr>
    </table>

    @yield('body')

    <table class="signature">
        <tr>
            <td>
                <div class="line">
                    {{ $parishPriest ?? '' }}<br>
                    Parish Priest
                </div>
            </td>
            <td class="seal">Parish seal</td>
        </tr>
    </table>
</body>
</html>
