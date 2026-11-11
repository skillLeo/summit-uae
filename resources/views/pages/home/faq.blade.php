@php use App\Support\SiteUrl; @endphp
{{-- 09 Questions Before Requesting a Consultation: native disclosures with visible H3 questions;
     the answers stay in the HTML. --}}
<section class="section section--ruled" id="questions" aria-labelledby="faq-title">
    <div class="container grid faq__grid">
        <div class="faq__head">
            <h2 id="faq-title">{{ $c['faq']['heading'] ?? '' }}</h2>
        </div>
        <div class="faq__body">
            <div class="disclosure-list">
                @foreach ($c['faq']['items'] ?? [] as $i => $item)
                    <details class="disclosure" id="home-faq-{{ $i + 1 }}">
                        <summary>
                            <h3 class="disclosure__q">{{ $item['question'] }}</h3>
                            <x-icon name="plus" />
                        </summary>
                        <div class="disclosure__a"><p>{{ $item['answer'] }}</p></div>
                    </details>
                @endforeach
            </div>
            <div class="section-foot">
                <a class="text-link" href="{{ SiteUrl::page('P05') }}">{{ ui('cta.view_all_faqs') }}</a>
            </div>
        </div>
    </div>
</section>
