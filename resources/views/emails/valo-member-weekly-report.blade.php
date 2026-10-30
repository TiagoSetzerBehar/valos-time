@component('mail::message')
# {{ __('Your week in VALO\'s Time') }}

{{ __('Hello :name,', ['name' => $user->name]) }}

{{ __('Week :week. Total: :total h.', ['week' => $weekLabel, 'total' => $total]) }}

@if(count($rows) === 0)
{{ __('You did not log any hours last week.') }}
@else
@component('mail::table')
| {{ __('Project') }} | {{ __('Hours') }} |
|:--|--:|
@foreach($rows as $row)
| {{ $row['project'] }} | {{ $row['hours'] }} |
@endforeach
@endcomponent
@endif

@component('mail::button', ['url' => $timeUrl])
{{ __('Open VALO\'s Time') }}
@endcomponent

@endcomponent
