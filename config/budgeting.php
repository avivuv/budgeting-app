<?php

/*
|--------------------------------------------------------------------------
| Budgeting defaults
|--------------------------------------------------------------------------
|
| Created for every new family (docs/03-multi-tenancy.md §6). Keywords are
| matched as whole words to guess a category from free text (BR-E05).
|
*/

return [

    'default_wallets' => [
        ['name' => 'Tunai', 'type' => 'cash', 'icon' => 'wallet', 'color' => '#16a34a'],
    ],

    'default_categories' => [
        'expense' => [
            ['name' => 'Makanan & Minuman', 'icon' => 'utensils', 'color' => '#f97316', 'keywords' => ['makan', 'minum', 'sarapan', 'makan siang', 'makan malam', 'kopi', 'jajan', 'snack', 'resto', 'restoran', 'warung', 'gofood', 'grabfood', 'shopeefood']],
            ['name' => 'Belanja Harian', 'icon' => 'shopping-basket', 'color' => '#22c55e', 'keywords' => ['belanja', 'sayur', 'beras', 'telur', 'minyak', 'gula', 'indomaret', 'alfamart', 'pasar', 'supermarket', 'galon']],
            ['name' => 'Transportasi', 'icon' => 'car', 'color' => '#0ea5e9', 'keywords' => ['bensin', 'pertalite', 'pertamax', 'solar', 'parkir', 'tol', 'grab', 'gojek', 'ojek', 'ojol', 'taksi', 'kereta', 'krl', 'busway', 'transjakarta', 'servis motor', 'servis mobil']],
            ['name' => 'Tagihan & Utilitas', 'icon' => 'receipt', 'color' => '#6366f1', 'keywords' => ['listrik', 'pln', 'token', 'air', 'pdam', 'internet', 'wifi', 'indihome', 'pulsa', 'paket data', 'bpjs', 'iuran']],
            ['name' => 'Pendidikan', 'icon' => 'graduation-cap', 'color' => '#8b5cf6', 'keywords' => ['spp', 'sekolah', 'les', 'kursus', 'buku', 'seragam', 'kuliah', 'ukt']],
            ['name' => 'Kesehatan', 'icon' => 'heart-pulse', 'color' => '#ef4444', 'keywords' => ['obat', 'apotek', 'dokter', 'klinik', 'rumah sakit', 'vitamin', 'periksa']],
            ['name' => 'Rumah Tangga', 'icon' => 'house', 'color' => '#14b8a6', 'keywords' => ['sabun', 'deterjen', 'gas', 'elpiji', 'lpg', 'perabot', 'perbaikan', 'laundry', 'art', 'pembantu']],
            ['name' => 'Hiburan', 'icon' => 'clapperboard', 'color' => '#ec4899', 'keywords' => ['nonton', 'bioskop', 'netflix', 'spotify', 'liburan', 'wisata', 'game', 'hotel']],
            ['name' => 'Pakaian', 'icon' => 'shirt', 'color' => '#a855f7', 'keywords' => ['baju', 'celana', 'sepatu', 'sandal', 'tas', 'kaos']],
            ['name' => 'Sosial & Donasi', 'icon' => 'hand-heart', 'color' => '#f59e0b', 'keywords' => ['sedekah', 'infak', 'zakat', 'donasi', 'kondangan', 'hadiah', 'arisan', 'sumbangan']],
            ['name' => 'Cicilan', 'icon' => 'landmark', 'color' => '#64748b', 'keywords' => ['cicilan', 'kredit', 'angsuran', 'kpr', 'paylater']],
            ['name' => 'Lain-lain', 'icon' => 'ellipsis', 'color' => '#94a3b8', 'keywords' => []],
        ],
        'income' => [
            ['name' => 'Gaji', 'icon' => 'briefcase', 'color' => '#16a34a', 'keywords' => ['gaji', 'gajian', 'salary', 'thr']],
            ['name' => 'Bonus', 'icon' => 'gift', 'color' => '#22c55e', 'keywords' => ['bonus', 'insentif', 'komisi']],
            ['name' => 'Usaha', 'icon' => 'store', 'color' => '#0ea5e9', 'keywords' => ['usaha', 'jualan', 'dagang', 'omzet', 'penjualan']],
            ['name' => 'Hadiah', 'icon' => 'party-popper', 'color' => '#ec4899', 'keywords' => ['hadiah', 'angpao', 'kado']],
            ['name' => 'Lain-lain', 'icon' => 'ellipsis', 'color' => '#94a3b8', 'keywords' => []],
        ],
    ],

];
