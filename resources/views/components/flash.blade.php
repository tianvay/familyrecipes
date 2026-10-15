@if (session('status'))
    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition.opacity
         class="fixed bottom-6 left-1/2 z-30 -translate-x-1/2 rounded-full bg-ink px-5 py-3 text-sm font-medium text-paper shadow-lg"
         role="status">
        {{ session('status') }}
    </div>
@endif
