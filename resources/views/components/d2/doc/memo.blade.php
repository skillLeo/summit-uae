@props([
    'variant' => 'stage',  // stage | mobile | still | anatomy
    'hot' => null,         // selected field id: reason | cheque | stamp
])
{{--
    Sample cheque return memo (300×150) on its own: a bank slip from "Sample Bank" with a folded corner
    (12 px), zeroed cheque number and amount, and the stated reason for return ("Insufficient funds").
    Generic: no real bank's form, return codes or logo. The cheque staples the same slip behind its top
    edge (d2.doc.memo-slip). Contract: d2.doc.frame.
--}}
<x-d2.doc.frame type="memo" :variant="$variant" {{ $attributes }}>
    <x-d2.doc.memo-slip :hot="$hot" />
</x-d2.doc.frame>
