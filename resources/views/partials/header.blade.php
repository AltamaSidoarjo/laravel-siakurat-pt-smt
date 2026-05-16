<a class="navbar-brand d-flex align-items-center gap-2 me-lg-4" href="{{ route('home') }}">
    <img
        src="{{ asset('assets/logo-jrsma.png') }}"
        alt="Logo"
        width="100"
        height="56"
        class="flex-shrink-0"
    >

    <div class="d-flex flex-column">
        <span class="fw-bold m-0" style="line-height: 1.1;">
            {{ config('siakurat.app_name') }}
        </span>
        <span class="m-0" style="line-height: 1.1; font-size: 0.85rem;">
            {{ config('siakurat.rs_name') }}
        </span>
    </div>
</a>

<button
    class="navbar-toggler"
    type="button"
    data-bs-toggle="collapse"
    data-bs-target="#mainNavbar"
    aria-controls="mainNavbar"
    aria-expanded="false"
    aria-label="Toggle navigation"
>
    <span class="navbar-toggler-icon"></span>
</button>

<div class="navbar-collapse show justify-content-between align-items-lg-center" id="mainNavbar">
    <div class="main-menu-wrapper">
        <ul class="navbar-nav nav-pills navbar-nav-scroll main-menu flex-wrap align-items-lg-center" style="--bs-scroll-height: 300px;">
            @include('partials.menu')
        </ul>
    </div>

    <form class="d-flex justify-content-center justify-content-lg-end my-3 my-lg-0 ms-lg-3" action="{{ route('logout') }}" method="post">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-light fw-bold">
            <i class="bi bi-caret-right-fill"></i> Logout
        </button>
    </form>
</div>
