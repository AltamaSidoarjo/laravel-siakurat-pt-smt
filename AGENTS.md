# AGENTS
## Project Overview
This project is built with Laravel and uses MySQL as the primary database.

The goal of this file is to define engineering conventions and implementation preferences for AI agents working in this repository. Follow these rules unless the user explicitly asks otherwise.

---

## Core Principles
- Prioritize readability, maintainability, and consistency over clever or overly abstract solutions.
- Keep implementations simple and aligned with Laravel best practices.
- Prefer clear separation of concerns.
- Avoid over-engineering.
- Before making changes, understand the surrounding code and follow existing project patterns where reasonable.

---

## Architecture Guidelines

### Controllers
- Controllers must remain thin.
- Controllers should only handle:
  - request input
  - validation coordination
  - calling application services
  - returning responses
- Do not place complex business logic in controllers.

### Services
- Place business logic in service classes.
- Use services for workflows, domain rules, orchestration, and reusable business processes.
- A service should have a clear responsibility.

### Repositories
- Use repositories when needed.
- Repositories are appropriate when:
  - database queries are complex
  - the same data access logic is reused in multiple places
  - data access should be isolated for maintainability or testing
- Do not introduce repositories for trivial CRUD if Eloquent usage is already clear and simple.

### Models
- Keep Eloquent models focused on data representation, relationships, casts, scopes, and simple model-level behavior.
- Do not place large business workflows inside models.
- Prefer explicit relationships, casts, and query scopes where useful.

### Requests / Validation
- Prefer Form Request classes for validation when applicable.
- Keep validation rules out of controllers when they become non-trivial.
- Use custom validation messages only when they improve clarity.

### Actions / Jobs / Events
- Use queued jobs for long-running or asynchronous tasks.
- Use events and listeners when they improve decoupling, not just for the sake of abstraction.
- Keep synchronous request-response flows simple unless asynchronous behavior is clearly needed.

---

## Database Conventions

### General
- Use MySQL-compatible queries and conventions.
- Prefer Laravel Eloquent and Query Builder over raw SQL unless raw SQL is necessary for performance or complexity reasons.
- Keep database access explicit and understandable.

### Migrations
- All schema changes must go through Laravel migrations.
- Do not modify the database structure outside migrations.
- Write reversible migrations whenever possible.
- Keep migrations focused and easy to review.

### Transactions
- Use database transactions when multiple write operations must succeed or fail together.
- Especially use transactions for workflows involving multiple related inserts, updates, or deletes.

### Query Design
- Avoid N+1 query problems.
- Use eager loading when appropriate.
- Select only necessary columns when performance matters.
- Prefer expressive query scopes or repository methods for reused filtering logic.

---

## Coding Standards

### PHP Style
- Follow PSR-12 for all PHP code.
- Follow existing project formatting and linting configuration if present.
- Use meaningful names for classes, methods, variables, and parameters.
- Prefer early returns to reduce nesting.
- Keep methods focused and reasonably short.

### Comments and Documentation
- Do not add unnecessary comments.
- Add PHPDoc or concise comments for:
  - public methods with important business behavior
  - non-obvious logic
  - side effects
  - important assumptions or constraints
- Avoid comments that merely restate the method name.

### Typing
- Use strict and explicit typing where appropriate.
- Add return types and parameter types consistently.
- Prefer typed properties and explicit value handling when supported by the project conventions.

### Error Handling
- Fail clearly and predictably.
- Use framework conventions for exceptions and error responses.
- Do not swallow exceptions silently.
- Log errors where operational visibility is important, but avoid noisy or redundant logging.

---

## Laravel-Specific Preferences

### Laravel Conventions
- Prefer Laravel conventions over custom patterns unless there is a strong reason not to.
- Use dependency injection through the service container.
- Prefer constructor injection for service dependencies.
- Use configuration files and environment variables properly; do not hardcode secrets or environment-specific values.

### Eloquent
- Prefer Eloquent relationships and scopes for common data access patterns.
- Avoid putting too much application logic in model boot methods unless clearly justified.
- Be careful with mass assignment and fillable/guarded behavior.

### Routing
- Keep routes clean and organized.
- Prefer route model binding when it improves clarity.
- Keep route definitions simple and avoid embedding business logic in route closures.

### API Design
- For APIs, prefer consistent response structures.
- Use Resources or transformers when response formatting becomes non-trivial.
- Preserve backward compatibility when modifying existing public endpoints unless the user explicitly requests breaking changes.

### Blade / Views
- Keep views focused on presentation.
- Do not place business logic in Blade templates.
- Prepare data in controllers, view models, or services before rendering.

---

## Testing Expectations
- Add or update tests for meaningful code changes.
- Prefer feature tests for end-to-end application behavior.
- Prefer unit tests for isolated business rules or service logic.
- Test critical business rules, edge cases, and failure scenarios.
- Do not remove tests without a clear reason.
- If a change affects existing behavior, review whether related tests should also be updated.

### Minimum Testing Rule
- New business logic should usually be covered by tests.
- Bug fixes should include a test that would have caught the bug when practical.

---

## File and Code Change Policy
- Make focused changes.
- Do not refactor unrelated parts of the code unless necessary for the requested task.
- Preserve existing architecture unless there is a clear benefit to changing it.
- Follow existing naming, folder structure, and coding patterns used by the repository.

---

## Security and Safety
- Never expose secrets, tokens, passwords, or sensitive configuration values.
- Do not hardcode credentials.
- Validate and sanitize user input appropriately.
- Apply authorization checks where needed.
- Be careful with file uploads, raw queries, dynamic execution, and external integrations.

---

## Performance Guidelines
- Be mindful of query efficiency.
- Avoid unnecessary loops over large datasets.
- Prefer pagination, chunking, or lazy processing where appropriate.
- Consider caching only when it provides clear value and remains maintainable.

---

## When Implementing New Features
Follow this order of thought:
1. Understand the existing flow and project conventions.
2. Identify the correct layer for the change.
3. Keep controllers thin.
4. Place business logic in services.
5. Introduce repositories only if they provide real value.
6. Update migrations if schema changes are required.
7. Add or update tests.
8. Keep the implementation simple, readable, and aligned with Laravel conventions.

---

## When Fixing Bugs
- First identify the root cause.
- Prefer minimal, targeted fixes.
- Check whether the bug is caused by validation, business logic, query logic, data consistency, or edge-case handling.
- Add or update tests for the fix whenever practical.

---

## Preferred Response Behavior for AI Agents
- Before changing code, inspect related files and understand the current implementation.
- Explain significant architectural choices briefly when relevant.
- If requirements are ambiguous, prefer the most conventional Laravel approach.
- If a requested pattern conflicts with existing code style, favor consistency unless the user explicitly requests a new pattern.
- Do not introduce unnecessary packages without clear justification.
- Do not rewrite large sections of code unless required.

---

## Default Folder Responsibilities
Use these conventions unless the repository already defines a different structure:
- app/Http/Controllers: HTTP layer only
- app/Http/Requests: validation logic
- app/Services: business logic and orchestration
- app/Repositories: reusable and non-trivial data access logic
- app/Models: Eloquent models and relationships
- app/Jobs: queued jobs
- app/Events: domain/application events
- app/Listeners: event listeners
- database/migrations: schema changes
- tests/Feature: integration / HTTP behavior
- tests/Unit: isolated logic

---

## Final Rule
When in doubt, prefer:
- Laravel conventions
- thin controllers
- service-based business logic
- repositories only where useful
- PSR-12 compliance
- clear, testable, maintainable code

---

## Documentation Policy

Project documentation lives in `dokumentasi/` and MUST stay in sync with the code. Treat
documentation updates as part of the change, not an afterthought.

### Core Rule
- Whenever you change code, features, endpoints, validation, business rules, or side effects for a
  module, you MUST update the corresponding document(s) in `dokumentasi/` in the same change.
- Keep documents accurate against the real code (routes, controller methods, service methods,
  request rules, ledger effects). Do not document assumptions.
- Follow the established documentation style (Ringkasan Modul, Entry Point/API table with the
  permission column, Validasi Request, per-flow Mermaid flowchart + Algoritma, Fungsi yang Dipanggil,
  Catatan Penting Bisnis). Use `dokumentasi/_template-modul.md` as the starting point.

### When Adding a New Module / Submodule
1. Create a new document in `dokumentasi/` from `_template-modul.md`.
2. Add it to the index table in `dokumentasi/README.md`.
3. Add a row to the Code → Documentation mapping table below.
4. If it introduces a new access module, also update `dokumentasi/fondasi/sistem-otorisasi.md`
   and `App\Support\AccessModuleRegistry`.

### When Changing Cross-Cutting Behavior
- Authorization / middleware / roles → update `dokumentasi/fondasi/sistem-otorisasi.md`.
- SIMRS connection or bridging read patterns → update `dokumentasi/fondasi/integrasi-simrs.md`.
- COA / Jurnal Umum / Buku Besar / `BukuBesarService` → update
  `dokumentasi/fondasi/konvensi-bukubesar-coa.md` (and affected module docs).
- Architecture, layering, or shared services → update `dokumentasi/fondasi/overview-arsitektur.md`.
- New domain terms → update `dokumentasi/fondasi/glosarium.md`.

### Code → Documentation Mapping

| Code (file/folder) | Documentation |
| --- | --- |
| `app/Http/Controllers/Auth/AuthController.php` | `dokumentasi/auth.md` |
| `app/Http/Controllers/HomeController.php`, `app/Services/HomeDashboardService.php` | `dokumentasi/home-dashboard.md` |
| `app/Http/Controllers/Bukubesar/JurnalUmumController.php`, `app/Services/Bukubesar/JurnalUmumService.php`, `app/Http/Requests/Bukubesar/*JurnalUmum*` | `dokumentasi/bukubesar-jurnal-umum.md` |
| `app/Http/Controllers/Bukubesar/CoaController.php`, `app/Services/Bukubesar/CoaService.php`, `app/Http/Requests/Bukubesar/*Coa*` | `dokumentasi/bukubesar-coa.md` |
| `app/Http/Controllers/Kasbank/KasbankPenerimaanController.php`, `app/Services/Kasbank/KasbankPenerimaanService.php`, `app/Http/Requests/Kasbank/*Penerimaan*` | `dokumentasi/kasbank-penerimaan.md` |
| `app/Http/Controllers/Kasbank/KasbankPembayaranController.php`, `app/Services/Kasbank/KasbankPembayaranService.php`, `app/Http/Requests/Kasbank/*Pembayaran*` | `dokumentasi/kasbank-pembayaran.md` |
| `app/Http/Controllers/Bridging/BridgingPendapatanController.php`, `app/Services/Bridging/BridgingPendapatanService.php`, `app/Http/Requests/Bridging/*Pendapatan*` (bukan obat) | `dokumentasi/bridging-pendapatan.md` |
| `app/Http/Controllers/Bridging/BridgingPendapatanObatController.php`, `app/Services/Bridging/BridgingPendapatanObatService.php`, `app/Http/Requests/Bridging/*PendapatanObat*` | `dokumentasi/bridging-pendapatan-obat.md` |
| `app/Http/Controllers/Bridging/BridgingPembelianController.php`, `app/Services/Bridging/BridgingPembelianService.php`, `app/Http/Requests/Bridging/*Pembelian*` | `dokumentasi/bridging-pembelian.md` |
| `app/Http/Controllers/Pendapatan/InvoicePendapatanController.php`, `app/Services/Pendapatan/InvoicePendapatanService.php` | `dokumentasi/pendapatan-invoice.md` |
| `app/Http/Controllers/Pendapatan/PenerimaanPendapatanController.php`, `app/Services/Pendapatan/PenerimaanPendapatanService.php`, `app/Http/Requests/Pendapatan/*` | `dokumentasi/pendapatan-penerimaan.md` |
| `app/Http/Controllers/Pembelian/InvoicePembelianController.php`, `app/Services/Pembelian/InvoicePembelianService.php` | `dokumentasi/pembelian-invoice.md` |
| `app/Http/Controllers/Pembelian/PembayaranPembelianController.php`, `app/Services/Pembelian/PembayaranPembelianService.php`, `app/Http/Requests/Pembelian/*` | `dokumentasi/pembelian-pembayaran.md` |
| `app/Http/Controllers/Laporan/LaporanKeuanganController.php`, `app/Services/Laporan/LaporanKeuanganService.php` | `dokumentasi/laporan-keuangan.md` |
| `app/Http/Controllers/Laporan/LaporanPendapatanController.php`, `app/Services/Laporan/LaporanPendapatanService.php` | `dokumentasi/laporan-pendapatan.md` |
| `app/Http/Controllers/Pengaturan/MappingPendapatanController.php`, `app/Services/Pengaturan/MappingPendapatanTindakanService.php`, `app/Http/Requests/Pengaturan/*MappingPendapatan*`, `*MappingLawanPendapatan*` | `dokumentasi/pengaturan-mapping-pendapatan.md` |
| `app/Http/Controllers/Pengaturan/MappingGeneralController.php`, `app/Services/Pengaturan/MappingGeneralService.php`, `app/Http/Requests/Pengaturan/*MappingGeneral*` | `dokumentasi/pengaturan-mapping-general.md` |
| `app/Http/Controllers/Pengaturan/SettingRbaController.php`, `app/Services/Pengaturan/SettingRbaService.php`, `app/Http/Requests/Pengaturan/*SettingRba*` | `dokumentasi/pengaturan-setting-rba.md` |
| `app/Http/Controllers/Pengaturan/PreferensiController.php`, `app/Services/Pengaturan/PreferensiService.php`, `app/Services/PreferensiPerusahaanService.php`, `app/Http/Requests/Pengaturan/*Preferensi*` | `dokumentasi/pengaturan-preferensi.md` |
| `app/Http/Controllers/Pengaturan/PenggunaController.php`, `app/Services/Pengaturan/UserManagementService.php`, `app/Http/Requests/Pengaturan/*User*` | `dokumentasi/pengaturan-pengguna.md` |
| `app/Http/Controllers/Pengaturan/RoleAksesController.php`, `app/Services/Pengaturan/RoleAccessManagementService.php`, `app/Http/Requests/Pengaturan/*RoleAccess*` | `dokumentasi/pengaturan-role-akses.md` |
| `app/Http/Controllers/Pengaturan/KonversiFileController.php`, `app/Services/Pengaturan/FileConversionService.php`, `app/Http/Requests/Pengaturan/ConvertCsvToXlsxRequest.php` | `dokumentasi/pengaturan-konversi-file.md` |
| `app/Http/Middleware/EnsureModuleAccess.php`, `app/Services/Auth/ModuleAccessService.php`, `app/Support/AccessModuleRegistry.php`, `app/Models/Role.php`, `RolePermission.php`, `AccessModule.php` | `dokumentasi/fondasi/sistem-otorisasi.md` |
| `config/database.php` (koneksi `simrs`), query `DB::connection('simrs')` di service Bridging | `dokumentasi/fondasi/integrasi-simrs.md` |
| `app/Services/Bukubesar/BukuBesarService.php`, `app/Models/Coa.php`, `TipeCoa.php`, `BukuBesar.php`, `JurnalUmum.php`, `JurnalUmumRinci.php` | `dokumentasi/fondasi/konvensi-bukubesar-coa.md` |
| `bootstrap/app.php`, `app/Providers/*`, struktur `app/` secara umum | `dokumentasi/fondasi/overview-arsitektur.md` |
| Istilah domain baru | `dokumentasi/fondasi/glosarium.md` |

### Pre-Completion Checklist (Documentation)
- [ ] Endpoint baru/berubah tercermin di tabel Entry Point/API dokumen terkait.
- [ ] Perubahan alur bisnis/efek samping (buku besar, log, transaksi) diperbarui di flowchart & algoritma.
- [ ] Perubahan aturan validasi (Form Request) diperbarui di bagian Validasi Request.
- [ ] Dokumen fondasi diperbarui bila perubahan bersifat lintas modul.
- [ ] `dokumentasi/README.md` dan tabel pemetaan di atas tetap konsisten (untuk modul baru).
