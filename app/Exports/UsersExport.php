<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class UsersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return User::all();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Nama',
            'Email',
            'NRP',
            'Role',
            'Departemen',
            'Status',
            'Tanggal Dibuat',
            'Tanggal Diperbarui',
        ];
    }

    public function map($user): array
    {
        return [
            $user->id,
            $user->name,
            $user->email,
            $user->nrp,
            $user->role,
            $user->department,
            $user->status ? 'Aktif' : 'Non-Aktif',
            $user->created_at,
            $user->updated_at,
        ];
    }
}
