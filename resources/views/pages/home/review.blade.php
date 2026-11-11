{{-- 04 What a Review of Your Matter Can Clarify: two paragraphs, five questions, and the
     four-step method on the spine (pinned with a scroll-driven highlight on desktop). --}}
@php $review = $c['review'] ?? []; @endphp
<section class="section section--ruled" id="review" aria-labelledby="review-title">
    <div class="container">
        <div class="grid review__grid">
            <div class="col-read review__intro">
                <h2 id="review-title">{{ $review['heading'] ?? '' }}</h2>
                @foreach ($review['paragraphs'] ?? [] as $p)
                    <p>{{ $p }}</p>
                @endforeach
            </div>

            <div class="col-full questions">
                <h3>{{ $review['questions_heading'] ?? '' }}</h3>
                <ul class="questions__list">
                    @foreach ($review['questions'] ?? [] as $q)
                        <li>{{ $q }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="method method--pinned" data-method>
            <div class="method__track">
                <div class="method__sticky">
                    <h3 class="method__heading">{{ $review['method_heading'] ?? '' }}</h3>
                    <div class="method__list">
                        <span class="method__rail" aria-hidden="true"></span>
                        <span class="method__progress" aria-hidden="true" data-method-progress></span>
                        <ol class="method__steps">
                            @foreach ($review['steps'] ?? [] as $i => $step)
                                <li class="method-step" data-method-step>
                                    <span class="method-step__marker" aria-hidden="true"><span class="ltr">{{ $i + 1 }}</span></span>
                                    <h4 class="method-step__title">{{ $step['title'] }}</h4>
                                    <p>{{ $step['text'] }}</p>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
