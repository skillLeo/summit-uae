{{-- Design 2 402 (Laravel ships a stock 402 page, which would win over errors/4xx): d2::errors.status with the 4xx copy. --}}
@include('d2::errors.status', ['status' => 402])
