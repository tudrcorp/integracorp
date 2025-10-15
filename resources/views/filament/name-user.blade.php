@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
<div>
    <span class="font-semibold text-gray-900 dark:text-white p-5 uppercase">
        {{ Auth::user()->name }}
    </span>
</div>