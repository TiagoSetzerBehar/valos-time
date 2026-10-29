@component('mail::message')
# {{ __('Weekly hours summary') }}

{{ __('Week :week. Expected hours per member: :hours h.', ['week' => $weekLabel, 'hours' => $expectedHours]) }}

@if(count($belowExpected) === 0)
{{ __('All :count members reached the expected hours.', ['count' => $memberCount]) }}
@else
{{ __('These members logged fewer hours than expected:') }}

@component('mail::table')
| {{ __('Member') }} | {{ __('Hours') }} |
|:--|--:|
@foreach($belowExpected as $row)
| {{ $row['name'] }} | {{ $row['hours'] }} |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => $reportUrl])
{{ __('Open the weekly report') }}
@endcomponent

@endcomponent
