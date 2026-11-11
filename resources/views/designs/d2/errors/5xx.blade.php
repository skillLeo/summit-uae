{{-- Design 2 catch-all for 5xx statuses without their own page (501, 502, 504 …): d2::errors.status. --}}
@include('d2::errors.status', ['status' => isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500])
