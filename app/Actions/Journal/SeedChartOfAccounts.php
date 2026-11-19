<?php

namespace App\Actions\Journal;

use App\Enums\AccountType;
use App\Enums\TradeAccountRole;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class SeedChartOfAccounts
{
    private const CASH_CODES = [1101, 1102, 1103, 1104, 1105];

    private const GROUPS = [
        [AccountType::Asset, [
            1101 => 'Kas',
            1102 => 'Bank BCA',
            1103 => 'Kas Kecil',
            1104 => 'Rekening Giro Bank',
            1105 => 'Rekening Tabungan Bank',
            1106 => 'Saldo Dompet Digital',
            1107 => 'Setoran dalam Perjalanan',
            1108 => 'Deposito Jangka Pendek',
            1109 => 'Rekening Bank Valuta Asing',
            1121 => 'Piutang Penyedia Pembayaran',
            1122 => 'Piutang Pihak Berelasi',
            1123 => 'Piutang Karyawan',
            1124 => 'Piutang Retensi',
            1125 => 'Piutang Lain-lain',
            1126 => 'Aset Kontrak Belum Ditagih',
            1129 => 'Penyisihan Kerugian Piutang',
            1202 => 'Uang Muka Perjalanan Dinas',
            1211 => 'Sewa Dibayar di Muka',
            1212 => 'Asuransi Dibayar di Muka',
            1213 => 'Langganan Dibayar di Muka',
            1219 => 'Biaya Dibayar di Muka Lainnya',
            1292 => 'PPh 22 Dibayar di Muka',
            1293 => 'PPh 23 Dibayar di Muka',
            1294 => 'PPh 25 Dibayar di Muka',
            1295 => 'Piutang Pajak',
            1301 => 'Persediaan Barang Dagangan',
            1302 => 'Persediaan Bahan Baku',
            1303 => 'Persediaan Barang dalam Proses',
            1304 => 'Persediaan Barang Jadi',
            1305 => 'Persediaan Bahan Penolong',
            1306 => 'Persediaan Barang dalam Perjalanan',
            1391 => 'Penyisihan Penurunan Nilai Persediaan',
            1401 => 'Perlengkapan Kantor',
            1501 => 'Tanah',
            1502 => 'Bangunan',
            1503 => 'Kendaraan',
            1504 => 'Mesin dan Peralatan Produksi',
            1505 => 'Inventaris Kantor',
            1506 => 'Komputer dan Perangkat TI',
            1507 => 'Renovasi Bangunan Sewa',
            1508 => 'Aset Tetap dalam Penyelesaian',
            1552 => 'Akumulasi Penyusutan Bangunan',
            1553 => 'Akumulasi Penyusutan Kendaraan',
            1554 => 'Akumulasi Penyusutan Mesin dan Peralatan Produksi',
            1555 => 'Akumulasi Penyusutan Inventaris Kantor',
            1556 => 'Akumulasi Penyusutan Komputer dan Perangkat TI',
            1557 => 'Akumulasi Penyusutan Renovasi Bangunan Sewa',
            1601 => 'Perangkat Lunak dan Lisensi',
            1602 => 'Akumulasi Amortisasi Aset Takberwujud',
            1701 => 'Investasi Jangka Panjang',
            1702 => 'Uang Jaminan Dibayarkan',
            1703 => 'Properti Investasi',
            1704 => 'Aset Pajak Tangguhan',
            1705 => 'Piutang Jangka Panjang',
        ]],
        [AccountType::Liability, [
            2101 => 'Utang Barang Diterima Belum Ditagih',
            2102 => 'Utang Pembelian Aset Tetap',
            2103 => 'Utang Pihak Berelasi Jangka Pendek',
            2109 => 'Utang Lain-lain',
            2111 => 'Beban Masih Harus Dibayar',
            2112 => 'Utang Gaji dan Upah',
            2113 => 'Utang Bonus dan THR',
            2114 => 'Utang Iuran BPJS',
            2115 => 'Utang Bunga',
            2121 => 'Pinjaman Bank Jangka Pendek',
            2122 => 'Bagian Lancar Pinjaman Jangka Panjang',
            2123 => 'Bagian Lancar Liabilitas Sewa',
            2152 => 'Utang PPN',
            2153 => 'Utang PPh 21',
            2154 => 'Utang PPh 23',
            2155 => 'Utang PPh 26',
            2156 => 'Utang PPh Pasal 4 Ayat 2',
            2157 => 'Utang PPh Badan',
            2201 => 'Pendapatan Jasa Diterima di Muka',
            2202 => 'Uang Jaminan Pelanggan',
            2203 => 'Liabilitas Pengembalian Dana',
            2301 => 'Pinjaman Bank Jangka Panjang',
            2302 => 'Liabilitas Sewa Jangka Panjang',
            2303 => 'Utang Pihak Berelasi Jangka Panjang',
            2304 => 'Liabilitas Imbalan Pascakerja',
            2305 => 'Liabilitas Pajak Tangguhan',
            2306 => 'Provisi Jangka Panjang',
        ]],
        [AccountType::Equity, [
            3101 => 'Modal Disetor',
            3102 => 'Tambahan Modal Disetor',
            3103 => 'Modal Pemilik',
            3104 => 'Prive Pemilik',
            3201 => 'Saldo Laba',
            3202 => 'Cadangan Umum',
            3203 => 'Distribusi Dividen',
            3301 => 'Komponen Ekuitas Lainnya',
        ]],
        [AccountType::Revenue, [
            4101 => 'Pendapatan Usaha',
            4111 => 'Penjualan Produk',
            4112 => 'Penjualan Grosir',
            4113 => 'Penjualan Ritel',
            4118 => 'Retur dan Pengurangan Penjualan',
            4119 => 'Potongan Penjualan',
            4120 => 'Pendapatan Jasa',
            4121 => 'Pendapatan Konsultasi',
            4122 => 'Pendapatan Pemeliharaan',
            4123 => 'Pendapatan Langganan',
            4124 => 'Pendapatan Instalasi',
            4125 => 'Pendapatan Pelatihan',
            4129 => 'Potongan dan Pengembalian Jasa',
            4130 => 'Pendapatan Proyek',
            4140 => 'Pendapatan Komisi',
            4150 => 'Pendapatan Sewa Operasional',
            7101 => 'Pendapatan Bunga dan Jasa Giro',
            7102 => 'Keuntungan Selisih Kurs',
            7103 => 'Keuntungan Pelepasan Aset Tetap',
            7104 => 'Pendapatan Lain-lain',
        ]],
        [AccountType::Expense, [
            5101 => 'Harga Pokok Penjualan Barang',
            5102 => 'Harga Pokok Barang Produksi',
            5103 => 'Penyesuaian dan Selisih Persediaan',
            5104 => 'Beban Penurunan Nilai Persediaan',
            5201 => 'Tenaga Kerja Langsung Jasa',
            5202 => 'Subkontraktor Proyek',
            5203 => 'Bahan dan Perlengkapan Proyek',
            5204 => 'Perjalanan dan Transportasi Proyek',
            5205 => 'Sewa Peralatan Proyek',
            5206 => 'Biaya Langsung Jasa Lainnya',
            6101 => 'Beban Gaji dan Upah',
            6102 => 'Beban Listrik',
            6103 => 'Beban Air',
            6104 => 'Beban Telepon dan Internet',
            6105 => 'Beban Sewa Kantor',
            6106 => 'Beban Alat Tulis dan Perlengkapan Kantor',
            6107 => 'Beban Kebersihan dan Keamanan',
            6108 => 'Beban Langganan Perangkat Lunak',
            6109 => 'Beban Administrasi dan Umum Lainnya',
            6111 => 'Beban Tunjangan dan THR',
            6112 => 'Beban Bonus dan Insentif',
            6113 => 'Beban Iuran BPJS Perusahaan',
            6114 => 'Beban Rekrutmen dan Pelatihan',
            6115 => 'Beban Imbalan Pascakerja',
            6121 => 'Beban Transportasi dan Kendaraan',
            6122 => 'Beban Perjalanan Dinas',
            6123 => 'Beban Perbaikan dan Pemeliharaan',
            6124 => 'Beban Asuransi',
            6125 => 'Beban Jasa Profesional',
            6126 => 'Beban Perizinan dan Legal',
            6127 => 'Beban Jamuan dan Representasi',
            6131 => 'Beban Iklan dan Promosi',
            6132 => 'Beban Komisi Penjualan',
            6133 => 'Beban Pengiriman ke Pelanggan',
            6134 => 'Beban Kemasan Penjualan',
            6135 => 'Beban Administrasi Marketplace',
            6136 => 'Beban Layanan Pembayaran',
            6141 => 'Beban Penyusutan Bangunan',
            6142 => 'Beban Penyusutan Kendaraan',
            6143 => 'Beban Penyusutan Mesin dan Peralatan',
            6144 => 'Beban Penyusutan Inventaris Kantor dan TI',
            6145 => 'Beban Amortisasi Aset Takberwujud',
            6146 => 'Beban Penyusutan Renovasi Bangunan Sewa',
            6151 => 'Beban Kerugian Piutang',
            6152 => 'Beban Penurunan Nilai Aset Lainnya',
            7201 => 'Beban Bunga dan Biaya Pinjaman',
            7202 => 'Beban Administrasi Bank',
            7203 => 'Kerugian Selisih Kurs',
            7204 => 'Kerugian Pelepasan Aset Tetap',
            7205 => 'Beban Lain-lain',
            8101 => 'Beban Pajak Penghasilan Kini',
            8102 => 'Beban atau Manfaat Pajak Tangguhan',
            8103 => 'Beban Pajak Penghasilan Final',
        ]],
    ];

    public function handle(int $teamId): void
    {
        DB::transaction(function () use ($teamId) {
            Team::query()->whereKey($teamId)->lockForUpdate()->firstOrFail();

            $existing = DB::table('journal_accounts')->where('team_id', $teamId)
                ->pluck('trade_role', 'code');
            $assignedRoles = $existing->filter()->flip();
            $rows = [];

            foreach (self::GROUPS as [$type, $accounts]) {
                foreach ($accounts as $code => $name) {
                    if (! $existing->has($code)) {
                        $rows[] = [
                            'team_id' => $teamId,
                            'code' => (string) $code,
                            'name' => $name,
                            'type' => $type->value,
                            'is_cash' => in_array($code, self::CASH_CODES, true),
                            'trade_role' => null,
                        ];
                    }
                }
            }

            foreach (TradeAccountRole::cases() as $role) {
                [$code, $name, $type] = $role->definition();
                if (! $existing->has($code) && ! $assignedRoles->has($role->value)) {
                    $rows[] = [
                        'team_id' => $teamId,
                        'code' => $code,
                        'name' => $name,
                        'type' => $type->value,
                        'is_cash' => false,
                        'trade_role' => $role->value,
                    ];
                }
            }

            foreach (array_chunk($rows, 100) as $batch) {
                DB::table('journal_accounts')->insert($batch);
            }
        }, attempts: 5);
    }
}
