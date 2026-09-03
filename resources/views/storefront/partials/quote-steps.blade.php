@props([
    'step' => 1,
])

<div class="sf-steps" aria-hidden="true">
    <i @class(['is-on' => $step >= 1])></i>
    <i @class(['is-on' => $step >= 2])></i>
    <i @class(['is-on' => $step >= 3])></i>
</div>
