{{-- Markdown mail: keep lines unindented. Four leading spaces would turn a line into a code block. --}}
<x-mail::message>
# Your enquiry has been sent

Hi {{ $enquiry->user->name }},

We have passed your message about **{{ $enquiry->property->title }}** to {{ $enquiry->property->agency->name }}. They will contact you at {{ $enquiry->user->email }}@if ($enquiry->phone) or {{ $enquiry->phone }}@endif.

<x-mail::panel>
{{ $enquiry->message }}
</x-mail::panel>

<x-mail::button :url="route('properties.show', $enquiry->property->slug)">
View the property
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
