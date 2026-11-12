{{-- Plain-text overdue alert (App\Mail\RequestOverdue): reference, waiting time and link only. --}}
reference: {!! $enquiry->reference !!}
received_at: {!! ($enquiry->received_at ?? $enquiry->created_at)->copy()->timezone((string) config('summit.contact.timezone', 'UTC'))->format('Y-m-d H:i e') !!}
waiting_business_hours: {!! $waited !!}
internal_target_business_hours: {!! $target !!}
open: {!! $url !!}
