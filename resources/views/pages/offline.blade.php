@extends('layouts.site')

{{-- Offline fallback served by the service worker. Approved contact details only. --}}
@section('content')
    <div class="container">
        <header class="page-head">
            <h1>{{ proposed('offline.title') }}</h1>
            <p>{{ proposed('offline.text') }}</p>
        </header>
        <div class="section">
            <ul class="channels">
                <li class="channel"><a class="channel__value" href="{{ setting('phone_href') }}"><x-icon name="phone" /><bdi dir="ltr">{{ setting('phone_display') }}</bdi></a></li>
                <li class="channel"><a class="channel__value" href="{{ setting('whatsapp_url') }}" target="_blank" rel="noopener noreferrer"><x-icon name="whatsapp" /><span>{{ ui('cta.whatsapp') }}</span><span class="sr-only">{{ proposed('external.new_tab') }}</span></a></li>
                <li class="channel"><a class="channel__value" href="{{ setting('email_href') }}"><x-icon name="mail" /><bdi dir="ltr">{{ setting('email') }}</bdi></a></li>
            </ul>
        </div>
    </div>
@endsection
