{{--
    The analytics consent bar (docs/briefs/design2-only-black-header-ga4.md C4). It exists only when this response
    printed the GA4 tag (App\Support\Analytics::active(): d2::partials.analytics ran in the head and returned an ID).
    First in the body after the skip link, so keyboard and screen-reader users meet it before the page; drawn at the
    bottom of the screen (resources/css/d2/components/consent.css): above the tab bar and the floating WhatsApp
    button on phones, never over them.

    Copy: approved only, the Privacy Policy file's cookie interface strings (L01, ui cookies.*): the banner title and
    text, the policy link (L01, same tab), "Reject Optional Tools" and "Accept Optional Tools" as two equal buttons
    (the same variant and size), the saved / failed messages (a toast). The footer's "Privacy Settings" button
    (cookies.footer_button, [data-consent-open]) reopens the bar.
    Hidden until resources/js/d2/modules/analytics.js has read the visitor's choice (cookie summit_consent, 6 months):
    shown when there is none. Without JavaScript the tag does not run, so nothing is shown.
--}}
@if (\App\Support\Analytics::active())
    <section class="consent" id="consent" aria-labelledby="consent-title" data-consent hidden
        data-consent-saved="{{ ui('cookies.saved') }}" data-consent-failed="{{ ui('cookies.save_failed') }}">
        <div class="consent__panel" data-overlay>
            <div class="consent__copy">
                <h2 class="consent__title" id="consent-title" tabindex="-1">{{ ui('cookies.banner_title') }}</h2>
                <p class="consent__text">{{ ui('cookies.banner_text') }} <a class="consent__link" href="{{ target_url('L01') }}">{{ ui('cookies.policy_link') }}</a></p>
            </div>
            <div class="consent__actions">
                <x-d2.ui.button variant="ink" size="44" class="consent__button" data-consent-choice="denied">{{ ui('cookies.reject_optional') }}</x-d2.ui.button>
                <x-d2.ui.button variant="ink" size="44" class="consent__button" data-consent-choice="granted">{{ ui('cookies.accept_optional') }}</x-d2.ui.button>
            </div>
        </div>
    </section>
@endif
