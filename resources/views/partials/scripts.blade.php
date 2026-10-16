<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/sweetalert2/sweetalert2.all.min.js') }}"></script>
@stack('pre-scripts')
@auth
    @php($reminderCfg = \App\Models\Setting::reminderConfig(auth()->user()))
    @if ($reminderCfg)
        <script id="reminders-data" type="application/json">@json($reminderCfg)</script>
    @endif
@endauth
<script src="{{ asset('js/gharas.js') }}?v=3"></script>
@if (session('success') || session('error'))
    <div id="flash-data" data-type="{{ session('error') ? 'error' : 'success' }}" data-message="{{ session('error') ?? session('success') }}" hidden></div>
@endif
@stack('scripts')
