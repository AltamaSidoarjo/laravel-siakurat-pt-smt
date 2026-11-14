# Dokumentasi Modul Pengaturan — Pengguna

## Ringkasan Modul

Modul **Pengguna** mengelola akun pengguna (user) beserta role yang menentukan hak aksesnya.

Implementasi utama:

- `routes/web.php`
- `app/Http/Controllers/Pengaturan/PenggunaController.php`
- `app/Http/Requests/Pengaturan/StoreUserRequest.php`
- `app/Http/Requests/Pengaturan/UpdateUserRequest.php`
- `app/Services/Pengaturan/UserManagementService.php`
- `app/Models/User.php`, `app/Models/Role.php`

## Entry Point / API

| Method | Path | Route Name | Controller Method | Izin |
| --- | --- | --- | --- | --- |
| `GET` | `/pengaturan/pengguna` | `pengaturan.pengguna.index` | `index()` | view |
| `GET` | `/pengaturan/pengguna/create` | `pengaturan.pengguna.create` | `create()` | create |
| `POST` | `/pengaturan/pengguna` | `pengaturan.pengguna.store` | `store()` | create |
| `GET` | `/pengaturan/pengguna/{user}/edit` | `pengaturan.pengguna.edit` | `edit()` | update |
| `PUT` | `/pengaturan/pengguna/{user}` | `pengaturan.pengguna.update` | `update()` | update |
| `DELETE` | `/pengaturan/pengguna/{user}` | `pengaturan.pengguna.destroy` | `destroy()` | delete |

## Alur

```mermaid
flowchart TD
    A["GET index"] --> B["UserManagementService::getAll()"]
    C["GET create/edit"] --> D{"User target is_system && actor bukan is_system?"}
    D -- Ya --> E["Tolak: 403 Forbidden"]
    D -- Tidak --> F["getRoleOptions(actor)"]
    G["POST store"] --> H["StoreUserRequest (role_id non-system untuk actor non-system) -> create()"]
    I["PUT update"] --> J{"User target is_system && actor bukan is_system?"}
    J -- Ya --> K["Tolak: 403 Forbidden"]
    J -- Tidak --> L["UpdateUserRequest -> update()"]
    M["DELETE /{user}"] --> N{"Menghapus akun sendiri?"}
    N -- Ya --> O["Tolak: tidak boleh hapus akun yang sedang dipakai"]
    N -- Tidak --> P{"User target role is_system?"}
    P -- Ya --> Q["Tolak: Pengguna dengan role sistem tidak dapat dihapus"]
    P -- Tidak --> R["delete(user, currentUser) -> Hapus + redirect sukses"]
```

### Algoritma

1. `index()` menampilkan seluruh user via `getAll()`. Tombol hapus hanya ditampilkan untuk pengguna non-sistem selain akun yang sedang login.
2. `create()`/`edit()` menyediakan opsi role via `getRoleOptions($actor)`. Bila aktor bukan user dengan role sistem (`is_system`), opsi role sistem tidak ditampilkan. Pengguna non-sistem yang mencoba membuka form edit user sistem akan ditolak dengan HTTP 403.
3. `store()`/`update()` memvalidasi bahwa aktor non-sistem hanya dapat menetapkan role non-sistem (`Rule::exists('roles', 'id')->where('is_system', false)`). Password disimpan ter-hash sesuai cast model `User`.
4. `destroy()` menolak penghapusan bila:
   - User yang dihapus adalah akun yang sedang login (`delete()` mengembalikan `false`).
   - User yang dihapus memiliki role sistem (`is_system = true`).

## Fungsi yang Dipanggil

- `PenggunaController::index()/create()/store()/edit()/update()/destroy()`
- `UserManagementService::getAll()/getRoleOptions()/create()/update()/delete()`

## Catatan Penting Bisnis

- User tidak dapat menghapus akun yang sedang ia gunakan.
- User dengan role sistem (`is_system = true`, mis. Superadmin/Administrator) dilindungi dan tidak dapat dihapus oleh siapa pun.
- User non-sistem tidak dapat mengedit pengguna dengan role sistem dan tidak dapat menetapkan role sistem ke akun mana pun guna mencegah eskalasi hak akses (privilege escalation).
- Form tambah dan edit user menerapkan `autocomplete="off"` dan `autocomplete="new-password"` guna mencegah autofill kredensial oleh browser.
- `role_id` menentukan hak akses modul (lihat [Sistem Otorisasi](fondasi/sistem-otorisasi.md)).
- Password disimpan ter-hash (cast `hashed` pada model `User`).
