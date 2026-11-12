{{-- Plain-text daily digest (App\Mail\RequestsDigest): figures and references only, never personal data. --}}
date: {!! $f['date'] !!}
received_last_24_hours: {!! $f['received'] !!}
waiting_for_first_review: {!! $f['waiting'] !!}
over_internal_target: {!! $f['overdue'] !!}
oldest_wait_business_hours: {!! $f['oldest_hours'] ?? '' !!}
@foreach ($f['references'] as $row)
- {!! $row['reference'] !!} waiting {!! $row['hours'] !!} business hours{!! $row['overdue'] ? ' (over target)' : '' !!}
@endforeach
inbox: {!! $f['url'] !!}
