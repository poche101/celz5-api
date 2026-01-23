<?php

namespace App\Exports;

use App\Models\Testimony;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TestimoniesExport implements FromCollection, WithHeadings
{
    public function collection()
    {
        return Testimony::select('id', 'full_name', 'title', 'format', 'testimony', 'created_at')->get();
    }

    public function headings(): array
    {
        return ['ID', 'Full Name', 'Title', 'Format', 'Testimony Content', 'Submitted At'];
    }
}
