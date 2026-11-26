<?php

namespace App\Http\Middleware;

use App\Support\ConsultationForm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The enquiry endpoint (POST /{locale}/enquiries) exists only while the consultation form is on: WhatsApp only (client
 * PDF item 7) answers 404 before anything is validated, stored or sent.
 */
final class EnsureConsultationForm
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(ConsultationForm::enabled(), 404);

        return $next($request);
    }
}
