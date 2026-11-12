# Pemilih Perusahaan Aktif di Header Konten

## Tujuan

Memindahkan kontrol pergantian perusahaan dari sidebar ke pojok kanan atas header konten agar konteks perusahaan aktif selalu terlihat dekat dengan isi halaman dan sidebar menjadi lebih ringkas.

## Perilaku

- Header menampilkan label `Perusahaan aktif` dan nama perusahaan yang sedang dipilih.
- Kontrol memakai `TeamSwitcher` yang sudah ada sehingga pergantian perusahaan, pembaruan current team, dan penyesuaian slug URL tetap memakai alur yang telah teruji.
- Daftar tetap menandai perusahaan aktif dan tetap menyediakan aksi pembuatan perusahaan baru.
- Kontrol lama di sidebar dihapus agar tidak ada duplikasi.
- Pada layar sempit, label pendamping boleh disembunyikan, tetapi nama perusahaan dan tombol dropdown tetap terlihat serta dapat digunakan dengan keyboard.
- Tampilan mengikuti token aplikasi dan tetap terbaca pada mode terang maupun gelap.

## Pendekatan yang Dipertimbangkan

1. Menambahkan switcher di header sambil mempertahankannya di sidebar. Akses lebih banyak, tetapi menduplikasi kontrol yang sama.
2. Memindahkan switcher dari sidebar ke header. Dipilih karena memenuhi permintaan dan menyederhanakan sidebar tanpa mengubah logika bisnis.
3. Membuat native select khusus. Lebih sederhana secara visual, tetapi menggandakan logika navigasi dan menghilangkan aksi pembuatan perusahaan.

## Batasan

- Tidak mengubah endpoint, middleware, session, atau aturan otorisasi perusahaan.
- Tidak mengubah urutan menu navigasi lainnya.
- Tidak memperkenalkan library dropdown baru; komponen dropdown yang sudah ada tetap digunakan.

## Verifikasi

- Pemeriksaan format dan tipe Svelte.
- Build produksi.
- Browser QA pada desktop dan lebar 375 px, termasuk mode gelap.
- Memastikan kontrol tidak lagi muncul di sidebar, perusahaan aktif tampil di kanan atas, dropdown dapat dibuka dengan keyboard, dan pergantian perusahaan tetap menggunakan route yang ada.
