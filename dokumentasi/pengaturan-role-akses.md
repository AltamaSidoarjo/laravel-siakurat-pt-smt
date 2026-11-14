# Dokumentasi Modul Pengaturan — Role Akses

## Ringkasan Modul

Modul **Role Akses** mengelola peran (role) dan matriks izin per modul
(`can_view`/`can_create`/`can_update`/`can_delete`). Role menentukan hak akses user (lihat
[Sistem Otorisasi](fondasi/sistem-otorisasi.md)).

Implementasi utama:

- `routes/web.php`
- `app/Http/Controllers/Pengaturan/RoleAksesController.php`
- `app/Http/Requests/Pengaturan/StoreRoleAccessRequest.php`
- `app/Http/Requests/Pengaturan/UpdateRoleAccessRequest.php`
- `app/Services/Pengaturan/RoleAccessManagementService.php`
- `app/Models/Role.php`, `app/Models/RolePermission.php`, `app/Models/AccessModule.php`

## Entry Point / API

| Method | Path | Route Name | Controller Method | Izin |
| --- | --- | --- | --- | --- |
| `GET` | `/pengaturan/role-akses` | `pengaturan.role-akses.index` | `index()` | view |
| `GET` | `/pengaturan/role-akses/create` | `pengaturan.role-akses.create` | `create()` | create |
| `POST` | `/pengaturan/role-akses` | `pengaturan.role-akses.store` | `store()` | create |
| `GET` | `/pengaturan/role-akses/{role}/edit` | `pengaturan.role-akses.edit` | `edit()` | update |
| `PUT` | `/pengaturan/role-akses/{role}` | `pengaturan.role-akses.update` | `update()` | update |

> Modul ini tidak memiliki endpoint `delete`.

## Alur

```mermaid
flowchart TD
    A["GET index"] --> B["RoleAccessManagementService::getAll()"]
    C["GET create"] --> D["getModulesGrouped() (matriks kosong)"]
    E["GET edit/{role}"] --> F{"role->is_system && actor bukan is_system?"}
    F -- Ya --> G["Tolak: Role sistem hanya dapat diubah oleh Superadmin"]
    F -- Tidak --> H{"role == actor->role && actor bukan is_system?"}
    H -- Ya --> I["Tolak: Tidak dapat mengubah role sendiri"]
    H -- Tidak --> J["getModulesGrouped() + getPermissionMatrix(role)"]
    K["POST store"] --> L["StoreRoleAccessRequest -> create()"]
    M["PUT update/{role}"] --> N{"role->is_system && actor bukan is_system?"}
    N -- Ya --> O["Tolak: Role sistem hanya dapat diubah oleh Superadmin"]
    N -- Tidak --> P{"role == actor->role && actor bukan is_system?"}
    P -- Ya --> Q["Tolak: Tidak dapat mengubah role sendiri"]
    P -- Tidak --> R["UpdateRoleAccessRequest -> update()"]
```

### Algoritma

1. `index()` menampilkan seluruh role via `getAll()`. Aksi edit ditampilkan terkunci (disabled) untuk role sistem dan role milik pengguna sendiri bila pengguna yang login adalah non-sistem.
2. `create()` menyediakan modul dikelompokkan (`getModulesGrouped()`) dengan matriks izin kosong.
3. `edit()` menyediakan modul + matriks izin role (`getPermissionMatrix()`), menolak perubahan jika role adalah sistem dan aktor bukan user sistem, atau jika aktor mencoba mengedit rolenya sendiri.
4. `store()`/`update()` menyimpan role dan matriks izinnya.

## Fungsi yang Dipanggil

- `RoleAksesController::index()/create()/store()/edit()/update()`
- `RoleAccessManagementService::getAll()/getModulesGrouped()/getPermissionMatrix()/create()/update()`

## Catatan Penting Bisnis

- Role bertanda `is_system` mengidentifikasi role bawaan sistem (seperti Administrator). Kode role pada role sistem bersifat readonly pada form.
- Hak akses modul pada role sistem hanya dapat disesuaikan oleh pengguna yang juga memiliki role sistem (Superadmin). Pengguna non-sistem ditolak bila mencoba mengubah role sistem.
- Pengguna non-sistem tidak dapat mengubah izin untuk role milik mereka sendiri guna mencegah eskalasi hak akses mandiri (self privilege escalation).
- Daftar modul yang dapat diberi izin mengikuti `AccessModuleRegistry` (lihat
  [Sistem Otorisasi](fondasi/sistem-otorisasi.md)).
- Perubahan izin role langsung memengaruhi hak akses seluruh user pada role tersebut.
