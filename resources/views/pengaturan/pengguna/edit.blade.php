@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <div class="row mb-3">
        <div class="col">
            <div class="d-flex align-items-center gap-3 fs-3">
                <a href="{{ route('pengaturan.pengguna.index') }}" class="text-dark">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <span class="fw-bold">Edit User</span>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col">
            <div class="card border-muhammadiyah mb-2">
                <div class="card-body">
                    @include('partials.validation-errors')

                    <form method="post" action="{{ route('pengaturan.pengguna.update', $user) }}">
                        @csrf
                        @method('PUT')

                        <div class="d-flex flex-column gap-3">
                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="name" class="form-label">Nama</label>
                                            <input type="text" name="name" id="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                                            <input type="text" name="nama_lengkap" id="nama_lengkap" class="form-control" value="{{ old('nama_lengkap', $user->nama_lengkap) }}">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="jabatan" class="form-label">Jabatan</label>
                                            <input type="text" name="jabatan" id="jabatan" class="form-control" value="{{ old('jabatan', $user->jabatan) }}">
                                        </div>
                                        <div class="col-12">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" name="email" id="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="role_id" class="form-label">Role Akses</label>
                                            <select name="role_id" id="role_id" class="form-select" required>
                                                <option value="">Pilih role</option>
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->id }}" @selected((string) old('role_id', $user->role_id) === (string) $role->id)>
                                                        {{ $role->nama }} ({{ $role->kode }})
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="password" class="form-label">Password Baru</label>
                                            <input type="password" name="password" id="password" class="form-control">
                                            <div class="form-text">Kosongkan jika tidak ingin mengganti password. Minimal 8 karakter.</div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="password_confirmation" class="form-label">Konfirmasi Password Baru</label>
                                            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-light shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-end gap-3">
                                        <a href="{{ route('pengaturan.pengguna.index') }}" class="btn btn-light fw-bold">
                                            <i class="bi bi-x-circle-fill"></i> Batal
                                        </a>
                                        <button type="submit" class="btn btn-warning fw-bold">
                                            <i class="bi bi-check-circle-fill"></i> Update
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
