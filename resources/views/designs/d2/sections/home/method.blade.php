{{--
    Design 2 home 04: What a Review of Your Matter Can Clarify (upgrade spec §12 C4; Design 2.1 D49, brief §5.1
    item 5). id="review" (the Design 1 anchor). Approved P01 review content only, in the DOCX order:
      H2 review.heading;
      the two paragraphs on the start side, and on the end side H3 review.questions_heading with the five questions
      as a plain list (the approved questions have no answers, so no accordion; no check marks: D04) — side by side
      from 1024 px (columns 1-6 and 8-12 of the grid), stacked below;
      H3 review.method_heading and the four workflow steps (<x-d2.home.steps>: approved titles and texts) in one row
      of four, numbered 01-04 (numbers are allowed on the four workflow steps, brief §3.6 rule 4); 2 x 2 on tablets,
      stacked on phones. Not cards: nothing here is interactive (brief §3.5).
    Design 2.1 Phase 2 (D49) removed the sample-document still-life that sat beside the questions (decoration only,
    brief §3.1) and moved the questions before the steps, as in the DOCX.
--}}
@php
    use App\Design\D2\Bidi;

    $review = $c['review'] ?? [];
    $reviewParagraphs = array_values(array_filter($review['paragraphs'] ?? [], fn ($p) => is_string($p) && trim($p) !== ''));
    $reviewSteps = array_values(array_filter($review['steps'] ?? [], fn ($step) => trim($step['title'] ?? '') !== ''));
    $reviewQuestions = array_values(array_filter($review['questions'] ?? [], fn ($q) => is_string($q) && trim($q) !== ''));
@endphp
<section class="section method" id="{{ $sectionId ?? 'review' }}" aria-labelledby="review-title">
    <div class="container">
        <h2 class="method__title" id="review-title">{{ $review['heading'] ?? '' }}</h2>

        <div class="grid method__intro">
            <div class="method__text">
                @foreach ($reviewParagraphs as $paragraph)
                    <p>{{ Bidi::isolate($paragraph) }}</p>
                @endforeach
            </div>

            @if ($reviewQuestions !== [])
                <div class="method__questions">
                    <h3 class="method__heading" id="review-questions-title">{{ $review['questions_heading'] ?? '' }}</h3>
                    <ul class="method__checklist" role="list" aria-labelledby="review-questions-title">
                        @foreach ($reviewQuestions as $question)
                            <li class="method__question">{{ Bidi::isolate($question) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @if ($reviewSteps !== [])
            <div class="method__how">
                <h3 class="method__heading" id="review-method-title">{{ $review['method_heading'] ?? '' }}</h3>
                <x-d2.home.steps :steps="$reviewSteps" labelledby="review-method-title" />
            </div>
        @endif
    </div>
</section>
