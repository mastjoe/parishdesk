{{--
    The certificate of marriage the parish issues from an entry in its
    marriage register.
--}}
@php
    $date = fn ($value) => $value?->format('j F Y');
    $parents = function (array $spouse, string $relation): ?string {
        $names = collect([$spouse['father'], $spouse['mother']])->filter();

        return $names->isEmpty() ? null : "{$relation} of {$names->implode(' and ')}";
    };
    $place = $record->elsewhere ? ($record->place ?? 'another parish') : $parish->name;
    $witnesses = $record->sponsors->pluck('name');
@endphp

@extends('pdf.layouts.letter')

@section('title', $title)

@section('body')
    <table class="meta">
        <tr>
            <td>{{ $record->registerReference() ? "Register: {$record->registerReference()}" : '' }}</td>
            <td class="right">Issued {{ $date(now($parish->timezone)) }}</td>
        </tr>
    </table>

    <h1>{{ $title }}</h1>

    <p>
        This is to certify that, according to the marriage register of {{ $parish->name }},
        the persons named below were united in Holy Matrimony on {{ $date($record->received_on) }}
        at {{ $place }}.
    </p>

    <table class="details">
        <tr>
            <th>Husband</th>
            <td>
                {{ $husband['name'] }}
                @if ($line = $parents($husband, 'son'))
                    <br>{{ $line }}
                @endif
                @if ($husband['parish'])
                    <br>Parish: {{ $husband['parish'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Wife</th>
            <td>
                {{ $wife['name'] }}
                @if ($line = $parents($wife, 'daughter'))
                    <br>{{ $line }}
                @endif
                @if ($wife['parish'])
                    <br>Parish: {{ $wife['parish'] }}
                @endif
            </td>
        </tr>
        <tr>
            <th>Date of marriage</th>
            <td>{{ $date($record->received_on) }}</td>
        </tr>
        <tr>
            <th>Place</th>
            <td>{{ $place }}</td>
        </tr>
        @if ($record->minister)
            <tr>
                <th>Officiating minister</th>
                <td>{{ $record->minister }}</td>
            </tr>
        @endif
        @if ($witnesses->isNotEmpty())
            <tr>
                <th>Witnesses</th>
                <td>{{ $witnesses->join(', ', ' and ') }}</td>
            </tr>
        @endif
    </table>

    <p>Given under my hand and the seal of the parish on {{ $date(now($parish->timezone)) }}.</p>
@endsection