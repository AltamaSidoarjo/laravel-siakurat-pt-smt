<div class="card border-0 shadow-sm bg-white rounded-3 p-2 p-md-2.5 kpi-stepper-sticky mb-4" id="kpiFormStepper">
    <div class="d-flex align-items-center justify-content-between gap-2 overflow-x-auto py-1 px-1">
        <!-- Step 1: Identitas Dasar -->
        <a href="#sectionIdentitas" class="kpi-step-item active d-flex align-items-center gap-2.5 px-3 py-2 rounded-3 text-decoration-none" data-target="sectionIdentitas">
            <div class="kpi-step-badge rounded-circle d-flex align-items-center justify-content-center fw-bold">1</div>
            <div class="kpi-step-content text-start">
                <div class="kpi-step-title fw-bold text-nowrap">Identitas Dasar</div>
                <div class="kpi-step-subtitle small text-muted text-nowrap">Formula & Satuan</div>
            </div>
        </a>

        <div class="kpi-step-divider flex-grow-1 d-none d-md-block"></div>

        <!-- Step 2: Target Evaluasi -->
        <a href="#sectionTargetRag" class="kpi-step-item d-flex align-items-center gap-2.5 px-3 py-2 rounded-3 text-decoration-none" data-target="sectionTargetRag">
            <div class="kpi-step-badge rounded-circle d-flex align-items-center justify-content-center fw-bold">2</div>
            <div class="kpi-step-content text-start">
                <div class="kpi-step-title fw-semibold text-nowrap">Target Evaluasi</div>
                <div class="kpi-step-subtitle small text-muted text-nowrap">Ambang Batas RAG</div>
            </div>
        </a>

        <div class="kpi-step-divider flex-grow-1 d-none d-md-block"></div>

        <!-- Step 3: Pemetaan Akun -->
        <a href="#sectionMappingCoa" class="kpi-step-item d-flex align-items-center gap-2.5 px-3 py-2 rounded-3 text-decoration-none" data-target="sectionMappingCoa">
            <div class="kpi-step-badge rounded-circle d-flex align-items-center justify-content-center fw-bold">3</div>
            <div class="kpi-step-content text-start">
                <div class="kpi-step-title fw-semibold text-nowrap">Pemetaan Akun</div>
                <div class="kpi-step-subtitle small text-muted text-nowrap">Mapping COA</div>
            </div>
        </a>
    </div>
</div>

<style>
    .kpi-stepper-sticky {
        position: sticky;
        top: 1rem;
        z-index: 9;
        border: 1px solid #e2e8f0 !important;
        backdrop-filter: blur(8px);
        background: rgba(255, 255, 255, 0.95) !important;
        transition: box-shadow 0.2s ease;
    }
    .kpi-step-item {
        transition: all 0.2s ease-in-out;
        cursor: pointer;
        color: #64748b;
        border: 1px solid transparent;
    }
    .kpi-step-item:hover {
        background-color: #f8fafc;
        color: #334155;
    }
    .kpi-step-item.active {
        background-color: #f0fdf4; /* Green subtle */
        border-color: #bbf7d0;
        color: #166534;
    }
    .kpi-step-badge {
        width: 34px;
        height: 34px;
        min-width: 34px;
        font-size: 0.875rem;
        background-color: #f1f5f9;
        color: #64748b;
        transition: all 0.2s ease-in-out;
    }
    .kpi-step-item.active .kpi-step-badge {
        background-color: #027e3f; /* Warna hijau aplikasi */
        color: #ffffff;
        box-shadow: 0 2px 4px rgba(2, 126, 63, 0.25);
    }
    .kpi-step-item.active .kpi-step-title {
        color: #027e3f;
    }
    .kpi-step-divider {
        height: 1px;
        background-color: #e2e8f0;
        min-width: 12px;
    }
    #sectionIdentitas, #sectionTargetRag, #sectionMappingCoa {
        scroll-margin-top: 6.5rem;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const stepItems = document.querySelectorAll('.kpi-step-item');
        const sectionIds = ['sectionIdentitas', 'sectionTargetRag', 'sectionMappingCoa'];
        const sections = sectionIds
            .map(id => document.getElementById(id))
            .filter(el => el !== null);

        stepItems.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const targetEl = document.getElementById(targetId);
                if (targetEl) {
                    targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    stepItems.forEach(si => si.classList.toggle('active', si === item));
                }
            });
        });

        if ('IntersectionObserver' in window && sections.length > 0) {
            const observerOptions = {
                root: null,
                rootMargin: '-20% 0px -60% 0px',
                threshold: 0
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const activeId = entry.target.id;
                        stepItems.forEach(item => {
                            item.classList.toggle('active', item.getAttribute('data-target') === activeId);
                        });
                    }
                });
            }, observerOptions);

            sections.forEach(sec => observer.observe(sec));
        }
    });
</script>
