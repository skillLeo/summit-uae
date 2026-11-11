{{-- Design 2 catch-all for 4xx statuses without their own page (400, 405, 408, 413, 422 …): d2::errors.status. --}}
@include('d2::errors.status', ['status' => isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400])
