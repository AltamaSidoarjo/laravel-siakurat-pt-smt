@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <div class="d-flex justify-content-center align-items-center auth-screen">
        <div class="card shadow auth-card">
            <div class="card-body">
                <h4 class="text-center mb-4">Login</h4>

                <form action="{{ route('login.store') }}" method="post">
                    @csrf
                    <input type="hidden" name="return_url" value="{{ request('return_url', route('home')) }}">

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            class="form-control"
                            autocomplete="email"
                            value="{{ old('email') }}"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="passwordInput">Password</label>
                        <div class="input-group">
                            <input
                                id="passwordInput"
                                name="password"
                                type="password"
                                class="form-control"
                                autocomplete="current-password"
                            >

                            <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                Show
                            </button>
                        </div>
                    </div>

                    <div class="form-check mb-3">
                        <input id="remember_me" name="remember_me" type="checkbox" class="form-check-input" value="1" {{ old('remember_me') ? 'checked' : '' }}>
                        <label class="form-check-label" for="remember_me">Ingat saya</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>

                @if ($errors->any())
                    <div class="alert alert-danger mt-3">
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const passwordInput = document.getElementById('passwordInput');
            const toggleButton = document.getElementById('togglePassword');

            toggleButton.addEventListener('click', function () {
                const isPassword = passwordInput.getAttribute('type') === 'password';
                passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
                toggleButton.textContent = isPassword ? 'Hide' : 'Show';
            });
        });
    </script>
@endpush
