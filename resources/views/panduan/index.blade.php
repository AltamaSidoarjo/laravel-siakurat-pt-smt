@extends('layouts.app')

@section('title', 'User Guide')

@push('styles')
<style>
    .user-guide-topics .list-group-item {
        border-left: 3px solid transparent;
        transition: all 0.15s ease-in-out;
    }
    .user-guide-topics .list-group-item:hover {
        background-color: rgba(25, 135, 84, 0.05);
        color: #198754;
    }
    .user-guide-topics .list-group-item.active {
        background-color: #198754;
        border-color: #198754;
        border-left: 3px solid #0f5132;
        color: #fff;
    }
    .user-guide-topics .list-group-item.active .text-muted,
    .user-guide-topics .list-group-item.active i {
        color: #fff !important;
    }
    .user-guide-content {
        line-height: 1.7;
        font-size: 0.975rem;
        color: #212529;
    }
    .user-guide-content h1 {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 1.25rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e9ecef;
        color: #198754;
    }
    .user-guide-content h2 {
        font-size: 1.35rem;
        font-weight: 600;
        margin-top: 1.75rem;
        margin-bottom: 0.85rem;
        color: #212529;
    }
    .user-guide-content h3 {
        font-size: 1.15rem;
        font-weight: 600;
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }
    .user-guide-content table {
        width: 100%;
        margin-bottom: 1.25rem;
        border-collapse: collapse;
        font-size: 0.9rem;
    }
    .user-guide-content table th,
    .user-guide-content table td {
        padding: 0.6rem 0.75rem;
        border: 1px solid #dee2e6;
        vertical-align: top;
    }
    .user-guide-content table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    .user-guide-content table tr:nth-child(even) td {
        background-color: #fbfcfd;
    }
    .user-guide-content blockquote {
        border-left: 4px solid #198754;
        background-color: #f8fdf9;
        padding: 0.75rem 1rem;
        margin: 1.25rem 0;
        border-radius: 0 0.375rem 0.375rem 0;
        color: #495057;
    }
    .user-guide-content blockquote p:last-child {
        margin-bottom: 0;
    }
    .user-guide-content pre {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 0.375rem;
        padding: 0.85rem 1rem;
        font-size: 0.875rem;
        overflow-x: auto;
    }
    .user-guide-content code {
        background-color: rgba(25, 135, 84, 0.08);
        color: #0f5132;
        padding: 0.15rem 0.35rem;
        border-radius: 0.25rem;
        font-size: 0.875em;
    }
    .user-guide-content pre code {
        background-color: transparent;
        color: inherit;
        padding: 0;
    }
    .user-guide-content img {
        max-width: 100%;
        height: auto;
        border-radius: 0.375rem;
        border: 1px solid #dee2e6;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin: 1rem 0;
    }
    .user-guide-content hr {
        margin: 1.75rem 0;
        border-top: 1px solid #dee2e6;
        opacity: 0.8;
    }
    .user-guide-content ul, .user-guide-content ol {
        padding-left: 1.4rem;
        margin-bottom: 1rem;
    }
    .user-guide-content li {
        margin-bottom: 0.25rem;
    }
</style>
@endpush

@section('content')
<div class="row mb-3">
    <div class="col">
        <div class="d-flex align-items-center gap-3 fs-3">
            <a href="{{ route('home') }}" class="text-dark text-decoration-none" title="Kembali ke Home">
                <i class="bi bi-arrow-left"></i>
            </a>
            <span class="fw-bold">User Guide</span>
        </div>
        <div class="text-muted small">Panduan dan Dokumentasi Penggunaan Sistem SiAkurat</div>
    </div>
</div>

<div class="row g-3">
    {{-- Left column: Topic Navigation & Search --}}
    <div class="col-12 col-lg-4 col-xl-3">
        <div class="card border-muhammadiyah shadow-sm h-100">
            <div class="card-header bg-white py-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input
                        type="text"
                        id="topic-search-input"
                        class="form-control border-start-0 ps-1"
                        placeholder="Cari topik panduan..."
                        autocomplete="off"
                        aria-label="Cari topik panduan"
                    >
                </div>
            </div>
            <div class="card-body p-0 user-guide-topics">
                <div class="list-group list-group-flush" id="topic-list">
                    @forelse ($topics as $topic)
                        @php
                            $isActive = $currentTopic && $currentTopic['slug'] === $topic['slug'];
                        @endphp
                        <a
                            href="{{ route('panduan.index', ['topic' => $topic['slug']]) }}"
                            class="list-group-item list-group-item-action py-3 px-3 d-flex align-items-center justify-content-between {{ $isActive ? 'active' : '' }}"
                            data-topic-title="{{ strtolower($topic['title']) }}"
                            data-topic-slug="{{ $topic['slug'] }}"
                        >
                            <div class="me-2 text-truncate">
                                <i class="bi bi-file-earmark-text me-2 {{ $isActive ? 'text-white' : 'text-success' }}"></i>
                                <span class="fw-medium">{{ $topic['title'] }}</span>
                            </div>
                            <i class="bi bi-chevron-right small {{ $isActive ? 'text-white' : 'text-muted' }}"></i>
                        </a>
                    @empty
                        <div class="p-4 text-center text-muted">
                            <i class="bi bi-journal-x fs-2 d-block mb-2 text-secondary"></i>
                            <span>Belum ada dokumen panduan yang tersedia.</span>
                        </div>
                    @endforelse
                </div>

                <div id="no-search-results" class="p-4 text-center text-muted d-none">
                    <i class="bi bi-search fs-3 d-block mb-2 text-secondary"></i>
                    <span>Tidak ada topik yang sesuai dengan kata kunci.</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Right column: Content Viewer --}}
    <div class="col-12 col-lg-8 col-xl-9">
        <div class="card border-muhammadiyah shadow-sm">
            <div class="card-body p-4 p-md-5">
                @if ($currentTopic)
                    <article class="user-guide-content">
                        {!! $currentTopic['html'] !!}
                    </article>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-book fs-1 d-block mb-3 text-secondary"></i>
                        <h5 class="fw-semibold">Pilih Topik Panduan</h5>
                        <p class="mb-0">Silakan pilih salah satu topik pada daftar sebelah kiri untuk membaca panduan.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('topic-search-input');
        const topicList = document.getElementById('topic-list');
        const noResults = document.getElementById('no-search-results');

        if (!searchInput || !topicList) return;

        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            const items = topicList.querySelectorAll('[data-topic-title]');
            let visibleCount = 0;

            items.forEach(function (item) {
                const title = item.getAttribute('data-topic-title') || '';
                const slug = item.getAttribute('data-topic-slug') || '';
                if (query === '' || title.includes(query) || slug.includes(query)) {
                    item.classList.remove('d-none');
                    visibleCount++;
                } else {
                    item.classList.add('d-none');
                }
            });

            if (noResults) {
                if (visibleCount === 0 && items.length > 0) {
                    noResults.classList.remove('d-none');
                } else {
                    noResults.classList.add('d-none');
                }
            }
        });
    });
</script>
@endsection
