{{-- Design 2 401 (Laravel ships a stock 401 page, which would win over errors/4xx): d2::errors.status with the 4xx copy. --}}
@include('d2::errors.status', ['status' => 401])
