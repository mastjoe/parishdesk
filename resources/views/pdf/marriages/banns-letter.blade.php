{{--
    A letter to another parish about a couple's banns: a request to publish
    them there, or a confirmation that they were published here unopposed.
--}}
@use('App\Enums\BannsLetter')
@php
    $date = fn ($value) => $value?->format('j F Y');
    $parents = function (?string $father, ?string $mother, string $relation): ?string {
        $names = collect([$father, $mother])->filter();

        return $names->isEmpty() ? null : "{$relation} of {$names->implode(' and ')}";
    };
    $weddingPlace = $application->wedding_parish ?? $parish->name;
@endphp

@extends('pdf.layouts.letter')

@section('title', $letter->title())

@section('body')
    <table class="meta">
        <tr>
            <td>Ref: {{ $application->application_no }}</td>
            <td class="right">{{ $date(now($parish->timezone)) }}</td>
        </tr>
    </table>

    <p>
        The Parish Priest<br>
        {{ $addressee }}
    </p>

    <h1>{{ $letter->title() }}</h1>

    <p>Dear Father,</p>

    @if ($letter === BannsLetter::Confirmation)
        <p>
            This is to certify that the banns of marriage between the persons named below were
            published in {{ $parish->name }} on {{ $application->publications->map(fn ($publication) => $date($publication->published_on))->join(', ', ' and ') }}.
        </p>
    @else
        <p>
            The persons named below intend to contract marriage
            @if ($application->proposed_date)
                on {{ $date($application->proposed_date) }}
            @endif
            at {{ $weddingPlace }}.
            As {{ $letter === BannsLetter::GroomParishRequest ? 'the groom' : 'the bride' }} belongs to your parish,
            we kindly ask you to publish their banns there.
        </p>
    @endif

    <table class="details">
        <tr>
            <th>Groom</th>
            <td>
                {{ $application->groom_name }}
                @if ($line = $parents($application->groom_father_name, $application->groom_mother_name, 'son'))
                    <br>{{ $line }}
                @endif
                <br>Parish: {{ $application->groom_parish ?? $parish->name }}
            </td>
        </tr>
        <tr>
            <th>Bride</th>
            <td>
                {{ $application->bride_name }}
                @if ($line = $parents($application->bride_father_name, $application->bride_mother_name, 'daughter'))
                    <br>{{ $line }}
                @endif
                <br>Parish: {{ $application->bride_parish ?? $parish->name }}
            </td>
        </tr>
        <tr>
            <th>Wedding</th>
            <td>
                {{ $weddingPlace }}
                @if ($application->proposed_date)
                    <br>{{ $date($application->proposed_date) }}
                @endif
            </td>
        </tr>
    </table>

    @if ($letter === BannsLetter::Confirmation)
        <p>
            No impediment to the marriage is outstanding. As far as this parish knows, the parties
            are free to contract marriage.
        </p>
    @else
        <p>
            Please publish the banns on three Sundays or holy days of obligation and let us know the
            result, stating whether any impediment was found.
        </p>
    @endif

    <p>Thank you, Father, and God bless you.</p>
@endsection