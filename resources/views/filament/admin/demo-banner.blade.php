{{--
    "Demo data" banner (docs/briefs/design-2.1-and-admin-cms.md §10.7): on every panel page while any sample request
    of `php artisan summit:demo-requests` exists. It names the command that removes them.
--}}
{{-- WhatsApp only (client PDF item 7): no requests screens, so no banner about their sample data. --}}
@php($count = \App\Support\ConsultationForm::enabled() ? \App\Models\Enquiry::query()->withTrashed()->where('is_demo', true)->count() : 0)
@if ($count > 0)
    <div class="slc-demo-banner" role="status" data-demo-banner>
        <x-filament::icon icon="heroicon-o-information-circle" class="slc-demo-banner__icon" />
        <p>{{ __('requests.demo.banner', ['count' => $count]) }}</p>
    </div>
@endif
