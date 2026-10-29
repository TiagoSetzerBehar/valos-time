@component('mail::message')
{{ __('Hello :name,', ['name' => $user->name]) }}

{{ __('You have not logged any hours today yet. It only takes a minute and helps the team see how the week is going.') }}

@component('mail::button', ['url' => $timeUrl])
{{ __('Log my hours') }}
@endcomponent

@endcomponent
