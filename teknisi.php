<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebGis Tekunin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { font-family: 'Plus Jakarta Sans', sans-serif; } 
        .ribbon-wrap { width: 120px; height: 120px; overflow: hidden; position: absolute; top: 0; right: 0; z-index: 10; pointer-events: none; }
        .ribbon { font: 700 9px/1 'Plus Jakarta Sans', sans-serif; text-align: center; text-transform: uppercase; transform: rotate(45deg); position: relative; padding: 7px 0; left: 18px; top: 18px; width: 130px; background-color: #f59e0b; color: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .glass-dark { background: rgba(8, 47, 73, 0.9); backdrop-filter: blur(16px); }
    </style>
    <script>
        // Login via sessionStorage saja (bukan PHP session → hindari SYSTEM HALT)
        try {
            if (sessionStorage.getItem('teknisi_logged') !== 'true') {
                window.location.replace('login_teknisi.php');
            }
        } catch (e) {
            // Storage diblokir (mode privat ketat) — tetap izinkan, user bisa login ulang
            console.warn('sessionStorage tidak tersedia', e);
        }
    </script>
</head>
<body class="bg-slate-100 text-slate-800 flex h-screen overflow-hidden">

    <!-- OVERLAY SIDEBAR MOBILE -->
    <div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-950/60 z-30 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

    <!-- SIDEBAR UTAMA -->
    <aside id="sidebar" class="fixed md:static inset-y-0 left-0 w-64 bg-white border-r border-slate-200 flex flex-col z-40 shrink-0 select-none -translate-x-full md:translate-x-0 transition-transform duration-300 shadow-xl md:shadow-none">
        <div class="h-16 border-b border-slate-200 flex items-center justify-between px-6">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-xl bg-sky-600 text-white flex items-center justify-center font-black text-sm shadow-sm"><i class="fa-solid fa-globe"></i></div>
                <span class="font-black text-slate-900 tracking-wide text-sm">Work Order</span>
            </div>
            <button onclick="toggleSidebar()" class="md:hidden text-slate-400 hover:text-slate-700"><i class="fa-solid fa-xmark text-lg"></i></button>
        </div>

        <div class="px-6 py-4 border-b border-slate-100 flex items-center gap-3 bg-slate-50/50">
            <div class="w-9 h-9 rounded-full bg-slate-300 flex items-center justify-center text-slate-600 font-bold text-xs"><i class="fa-solid fa-user"></i></div>
            <div class="overflow-hidden">
                <div class="text-xs font-black text-slate-900 truncate" id="sidebar-username">staff_teknis</div>
                <div class="text-[10px] text-emerald-600 font-bold flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Online</div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto py-4 px-3 space-y-1 text-xs font-bold text-slate-600">
            <!-- MENU WORK ORDER DENGAN DROPDOWN -->
            <div class="pt-2 pb-1">
                <button onclick="toggleWorkOrderDropdown()" class="w-full flex items-center justify-between px-3 py-2.5 rounded-xl bg-sky-600 text-white shadow-sm transition-all focus:outline-none">
                    <span class="flex items-center gap-3"><i class="fa-solid fa-clipboard-list w-5"></i> Work Order</span>
                    <i id="wo-chevron" class="fa-solid fa-chevron-down text-[10px] transition-transform duration-200"></i>
                </button>
                <div id="wo-submenu" class="pl-4 pr-1 py-2 space-y-1 mt-1 border-l-2 border-sky-100 ml-3 transition-all">
                    <a href="#" onclick="switchMenu('wo-instalasi')" id="menu-wo-instalasi" class="block px-3 py-2 rounded-lg bg-sky-50 text-sky-700 font-extrabold text-[11px]">W.O Instalasi</a>
                    <a href="#" onclick="switchMenu('wo-maintenance')" id="menu-wo-maintenance" class="block px-3 py-2 rounded-lg hover:bg-slate-100 text-slate-600 text-[11px]">W.O Maintenance</a>
                    <a href="#" onclick="switchMenu('wo-history')" id="menu-wo-history" class="block px-3 py-2 rounded-lg hover:bg-slate-100 text-slate-600 text-[11px]">W.O History</a>
                </div>
            </div>

            <!-- MENU STOK BARANG -->
            <div class="pt-1">
                <button type="button" onclick="switchMenu('stok')" id="menu-stok" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 text-slate-600 transition-all focus:outline-none">
                    <i class="fa-solid fa-boxes-stacked w-5"></i> Stok Barang
                </button>
            </div>
        </div>
    </aside>

    <!-- AREA KONTEN UTAMA -->
    <div class="flex-1 flex flex-col overflow-hidden w-full">
        
        <header class="h-16 bg-white border-b border-slate-200 flex justify-between items-center px-4 md:px-6 shrink-0 z-10">
            <div class="flex items-center gap-3">
                <button onclick="toggleSidebar()" class="md:hidden text-slate-700 text-lg p-2 rounded-xl hover:bg-slate-100"><i class="fa-solid fa-bars"></i></button>
                <div class="text-xs md:text-sm font-black text-slate-800 truncate" id="header-title">Work Order Instalasi</div>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-xs text-slate-500 font-medium hidden sm:inline" id="breadcrumb">Home / Work Order Instalasi</span>
                <button onclick="logoutTeknisi()" class="bg-rose-50 text-rose-600 hover:bg-rose-100 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors whitespace-nowrap"><i class="fa-solid fa-right-from-bracket mr-1"></i> Keluar</button>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-4 md:p-6 bg-slate-100 space-y-6">
            
            <!-- PANEL WORK ORDER INSTALASI -->
            <div id="panel-wo-instalasi" class="space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div>
                        <h3 class="font-black text-slate-900 text-base">Daftar Pesanan Work Order Masuk</h3>
                        <p class="text-xs text-slate-500">Terima WO terlebih dahulu sebelum mengisi form laporan lapangan.</p>
                    </div>
                </div>
                <div id="active-orders-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>
            </div>

            <!-- PANEL WORK ORDER MAINTENANCE -->
            <div id="panel-wo-maintenance" class="space-y-6 hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div>
                        <h3 class="font-black text-slate-900 text-base">Daftar Work Order Maintenance (Kendala Pelanggan)</h3>
                        <p class="text-xs text-slate-500">Daftar tiket kendala dan laporan perbaikan yang dikirimkan oleh user secara realtime.</p>
                    </div>
                </div>
                <div id="maintenance-orders-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>
            </div>

            <!-- PANEL WORK ORDER HISTORY -->
            <div id="panel-wo-history" class="space-y-6 hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div>
                        <h3 class="font-black text-slate-900 text-base">Work Order History</h3>
                        <p class="text-xs text-slate-500">Daftar laporan pengerjaan lapangan. Data akan otomatis tersinkron dan terhapus jika dihapus oleh admin.</p>
                    </div>
                </div>
                <div id="tech-history-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5"></div>
            </div>


            <!-- PANEL STOK BARANG (sinkron realtime dengan Admin & teknisi lain) -->
            <div id="panel-stok" class="space-y-6 hidden">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
                    <div>
                        <h3 class="font-black text-slate-900 text-base">Stok Barang</h3>
                        <p class="text-xs text-slate-500">Data stok dipakai bersama Admin dan semua teknisi. Perubahan dari siapa pun tampil otomatis.</p>
                    </div>
                    <span id="stok-live" class="text-[11px] font-extrabold px-3 py-1.5 rounded-full bg-slate-100 text-slate-500 flex items-center gap-1.5 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> Menghubungkan...</span>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5">
                    <h4 class="font-black text-slate-900 text-sm mb-4"><i class="fa-solid fa-pen-to-square text-sky-600 mr-1"></i> Catat Barang Masuk / Keluar</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div>
                            <label class="block font-bold text-slate-600 uppercase mb-1">Serial Number (SN)</label>
                            <input type="text" id="tstok-sn" maxlength="100" placeholder="Cth: SN-DVR-0001" class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-mono font-bold focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 uppercase mb-1">Grup</label>
                            <input type="text" id="tstok-grup" maxlength="100" list="tstok-grup-list" placeholder="Cth: DVR / Kamera / HDD" class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-bold focus:outline-none focus:border-sky-500">
                            <datalist id="tstok-grup-list"></datalist>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 uppercase mb-1">Jumlah</label>
                            <input type="number" id="tstok-jumlah" min="0" step="any" inputmode="decimal" placeholder="Cth: 10" class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-bold focus:outline-none focus:border-sky-500">
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 uppercase mb-1">Satuan</label>
                            <input type="text" id="tstok-satuan" maxlength="30" list="tstok-satuan-list" placeholder="Cth: Unit" class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-bold focus:outline-none focus:border-sky-500">
                            <datalist id="tstok-satuan-list"><option value="Unit"><option value="Pcs"><option value="Meter"><option value="Roll"><option value="Set"><option value="Box"></datalist>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 uppercase mb-1">Brand</label>
                            <input type="text" id="tstok-brand" maxlength="100" list="tstok-brand-list" placeholder="Cth: Hikvision" class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-bold focus:outline-none focus:border-sky-500">
                            <datalist id="tstok-brand-list"></datalist>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-600 uppercase mb-1">Jenis Transaksi</label>
                            <select id="tstok-tipe" class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-bold focus:outline-none focus:border-sky-500">
                                <option value="MASUK">Barang Masuk (+)</option>
                                <option value="KELUAR">Barang Keluar (-)</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-5 pt-4 border-t border-slate-100">
                        <button type="button" onclick="tkStockReset()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold">Reset</button>
                        <button type="button" id="tstok-save-btn" onclick="tkStockSave()" class="px-5 py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-extrabold shadow-md flex items-center gap-2"><i class="fa-solid fa-floppy-disk"></i> Simpan Transaksi</button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Stok Saat Ini</h4>
                        <div class="flex items-center gap-2">
                            <input type="text" id="tstok-search" oninput="tkStockRenderTable()" placeholder="Cari SN / grup / brand..." class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-sky-500 w-44 md:w-60">
                            <span id="tstok-count" class="text-xs bg-emerald-100 text-emerald-700 font-extrabold px-3 py-1 rounded-full whitespace-nowrap">0 Data</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto max-h-[380px] overflow-y-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 font-bold">SN</th>
                                    <th class="px-4 py-3 font-bold">Grup</th>
                                    <th class="px-4 py-3 font-bold text-right">Stok</th>
                                    <th class="px-4 py-3 font-bold">Satuan</th>
                                    <th class="px-4 py-3 font-bold">Brand</th>
                                    <th class="px-4 py-3 font-bold text-center">Isi Form</th>
                                </tr>
                            </thead>
                            <tbody id="tstok-body" class="divide-y divide-slate-100 font-medium text-slate-700"></tbody>
                        </table>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap bg-slate-50/50">
                        <h4 class="font-bold text-slate-900 text-sm">Riwayat Transaksi</h4>
                        <div class="flex items-center gap-2">
                            <select id="tstok-hist-filter" onchange="tkStockRenderHistory()" class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-bold focus:outline-none focus:border-sky-500 cursor-pointer">
                                <option value="">Semua</option>
                                <option value="INST">Instalasi</option>
                                <option value="MT">Maintenance</option>
                                <option value="MANUAL">Manual</option>
                            </select>
                            <input type="text" id="tstok-hist-search" oninput="tkStockRenderHistory()" placeholder="Cari SN / grup / kode WO..." class="px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs font-medium focus:outline-none focus:border-sky-500 w-44 md:w-60">
                            <span id="tstok-hist-count" class="text-xs bg-sky-100 text-sky-700 font-extrabold px-3 py-1 rounded-full whitespace-nowrap">0 Data</span>
                        </div>
                    </div>
                    <div class="overflow-x-auto max-h-[380px] overflow-y-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500 sticky top-0">
                                <tr>
                                    <th class="px-4 py-3 font-bold whitespace-nowrap">Waktu</th>
                                    <th class="px-4 py-3 font-bold">Jenis</th>
                                    <th class="px-4 py-3 font-bold">SN</th>
                                    <th class="px-4 py-3 font-bold">Grup</th>
                                    <th class="px-4 py-3 font-bold text-right">Jumlah</th>
                                    <th class="px-4 py-3 font-bold">Brand</th>
                                    <th class="px-4 py-3 font-bold">Oleh / Kode WO</th>
                                </tr>
                            </thead>
                            <tbody id="tstok-hist-body" class="divide-y divide-slate-100 font-medium text-slate-700"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div id="panel-placeholder" class="bg-white rounded-2xl p-12 text-center text-slate-400 font-bold hidden">
                Modul menu sedang dimuat...
            </div>

        </main>

        <footer class="h-12 bg-white border-t border-slate-200 flex justify-between items-center px-6 text-[11px] text-slate-500 shrink-0">
            <span>Copyright © 2026 CV Teknologi Untuk Indonesia</span>
            <span>Brand: <b>Tekunin CCTV</b></span>
        </footer>
    </div>

    <!-- MODAL POPUP FORM INPUT TEKNISI INSTALASI -->
    <div id="form-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
                <h4 class="font-black text-slate-900 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench text-sky-600"></i> Form Input Teknisi Lapangan (Instalasi)
                </h4>
                <button onclick="closeFormModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form onsubmit="submitInstalasiTeknisi(event); return false;" action="javascript:void(0)" method="get" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Nama Pelanggan</label>
                    <input type="text" id="modal-cust-name" readonly class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl font-bold text-slate-800 focus:outline-none">
                    <input type="hidden" id="modal-cust-id">
                    <input type="hidden" id="modal-invoice-id">
                </div>

                <!-- GRUP SN & FOTO DVR / NVR -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-server text-sky-600 mr-1"></i> Perangkat DVR / NVR</label>
                        <button type="button" onclick="addDeviceWithPhoto('dvr-container', 'DVR/NVR', 't-dvr', 't-foto-dvr', 'Cth: DVR-983472', 'sky')" class="px-2 py-1 bg-sky-50 text-sky-600 hover:bg-sky-100 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="dvr-container" class="space-y-3"></div>
                </div>

                <!-- GRUP SN & FOTO CCTV -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-video text-sky-600 mr-1"></i> Perangkat CCTV (Kamera)</label>
                        <button type="button" onclick="addDeviceWithPhoto('cctv-container', 'CCTV', 't-cctv', 't-foto-cctv', 'Cth: CAM-552140', 'sky')" class="px-2 py-1 bg-sky-50 text-sky-600 hover:bg-sky-100 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="cctv-container" class="space-y-3"></div>
                </div>

                <!-- GRUP SN & FOTO HARDDISK -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-hard-drive text-sky-600 mr-1"></i> Perangkat Harddisk (HDD)</label>
                        <button type="button" onclick="addDeviceWithPhoto('hdd-container', 'Harddisk', 't-hdd', 't-foto-hdd', 'Cth: WDC-TB1-8832', 'sky')" class="px-2 py-1 bg-sky-50 text-sky-600 hover:bg-sky-100 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="hdd-container" class="space-y-3"></div>
                </div>

                <!-- GRUP SN & FOTO & DESKRIPSI PERANGKAT LAIN -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-plug text-sky-600 mr-1"></i> SN Perangkat Lain</label>
                        <button type="button" onclick="addDeviceWithPhoto('other-container', 'Perangkat Lain', 't-other', 't-foto-other', 'Cth: PSU-12V-2231', 'sky', false)" class="px-2 py-1 bg-sky-50 hover:bg-sky-100 text-sky-600 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="other-container" class="space-y-3"></div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <label class="block font-bold text-slate-600 uppercase mb-1">Jumlah Kabel (Meter)</label>
                    <input type="number" id="t-kabel" placeholder="Cth: 35" required class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-bold focus:outline-none focus:border-sky-500">
                    <label class="block font-bold text-slate-500 text-[10px] uppercase mt-2 mb-1"><i class="fa-solid fa-comment-dots mr-1"></i> Deskripsi Kabel <span class="normal-case font-medium text-slate-400">(opsional)</span></label>
                    <textarea id="t-kabel-desc" rows="2" maxlength="500" placeholder="Cth: kabel RG59 + power, jalur lewat plafon" class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-medium focus:outline-none focus:border-sky-500 resize-none"></textarea>
                </div>

                <p class="text-[10px] text-slate-500 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2 leading-relaxed">
                    <i class="fa-solid fa-circle-info text-amber-600 mr-1"></i>
                    Di HP: pilih foto JPG/PNG dari galeri. Hindari HEIC. Foto dikompres otomatis agar tetap terkirim di luar jaringan kantor. SN baru ditambahkan, SN lama tidak dihapus.
                </p>

                <div class="pt-2">
                    <button type="submit" id="submit-btn" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-extrabold shadow-md transition-all">
                        <i class="fa-solid fa-circle-check mr-1"></i> Selesaikan & Kirim ke Admin & User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL POPUP FORM MAINTENANCE TEKNISI -->
    <div id="form-modal-maint" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-950/70 backdrop-blur-sm">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-100">
                <h4 class="font-black text-rose-700 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench text-rose-600"></i> Form Laporan WO Maintenance
                </h4>
                <button onclick="closeFormModalMaint()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-rose-50 hover:text-rose-600 flex items-center justify-center transition-colors"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <form onsubmit="submitMaintenanceTeknisi(event); return false;" action="javascript:void(0)" method="get" class="space-y-4 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 uppercase mb-1">Tiket / Pelanggan</label>
                    <input type="text" id="maint-modal-cust-name" readonly class="w-full px-3 py-2.5 bg-slate-100 border border-slate-200 rounded-xl font-bold text-slate-800 focus:outline-none">
                    <input type="hidden" id="maint-modal-ticket-id">
                    <input type="hidden" id="maint-modal-cust-id">
                </div>

                <!-- GRUP SN & FOTO DVR / NVR MAINTENANCE -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-server text-rose-600 mr-1"></i> DVR / NVR (Pengantian/Update)</label>
                        <button type="button" onclick="addDeviceWithPhoto('maint-dvr-container', 'DVR/NVR', 'm-dvr', 'm-foto-dvr', 'Cth: DVR-983472', 'rose', false)" class="px-2 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="maint-dvr-container" class="space-y-3"></div>
                </div>

                <!-- GRUP SN & FOTO CCTV MAINTENANCE -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-video text-rose-600 mr-1"></i> CCTV (Pengantian/Update)</label>
                        <button type="button" onclick="addDeviceWithPhoto('maint-cctv-container', 'CCTV', 'm-cctv', 'm-foto-cctv', 'Cth: CAM-552140', 'rose', false)" class="px-2 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="maint-cctv-container" class="space-y-3"></div>
                </div>

                <!-- GRUP SN & FOTO HARDDISK MAINTENANCE -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-hard-drive text-rose-600 mr-1"></i> Harddisk (Pengantian/Update)</label>
                        <button type="button" onclick="addDeviceWithPhoto('maint-hdd-container', 'Harddisk', 'm-hdd', 'm-foto-hdd', 'Cth: WDC-TB1-8832', 'rose', false)" class="px-2 py-1 bg-rose-50 text-rose-600 hover:bg-rose-100 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="maint-hdd-container" class="space-y-3"></div>
                </div>

                <!-- GRUP SN & FOTO & DESKRIPSI PERANGKAT LAIN -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex justify-between items-center">
                        <label class="font-bold text-slate-700 uppercase"><i class="fa-solid fa-plug text-rose-600 mr-1"></i> SN Perangkat Lain</label>
                        <button type="button" onclick="addDeviceWithPhoto('maint-other-container', 'Perangkat Lain', 'm-other', 'm-foto-other', 'Cth: PSU-12V-2231', 'rose', false)" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg text-[10px] font-extrabold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-plus"></i> Tambah Unit
                        </button>
                    </div>
                    <div id="maint-other-container" class="space-y-3"></div>
                </div>

                <div class="pt-2 border-t border-slate-100">
                    <label class="block font-bold text-slate-600 uppercase mb-1">Deskripsi Perbaikan (Apa yang diperbaiki)</label>
                    <textarea id="m-deskripsi" rows="3" placeholder="Contoh: Penggantian Unit CAM 002 karena kerusakan sensor lensa..." required class="w-full px-3 py-2.5 bg-white border border-slate-300 rounded-xl font-bold focus:outline-none focus:border-rose-500 resize-none"></textarea>
                </div>

                <p class="text-[10px] text-slate-500 bg-amber-50 border border-amber-100 rounded-xl px-3 py-2 leading-relaxed">
                    <i class="fa-solid fa-circle-info text-amber-600 mr-1"></i>
                    Di HP: pilih foto JPG/PNG. SN baru akan <b>ditambahkan</b> ke data member, SN lama tidak dihapus.
                </p>

                <div class="pt-2">
                    <button type="submit" id="maint-submit-btn" class="w-full py-3 bg-rose-600 hover:bg-rose-500 text-white rounded-xl font-extrabold shadow-md transition-all">
                        <i class="fa-solid fa-paper-plane mr-1"></i> Kirim Laporan & Sinkron ke Member & Admin
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-6 right-6 md:bottom-8 md:right-8 z-[100] flex flex-col gap-3 pointer-events-none w-[calc(100%-48px)] md:w-auto"></div>

    <script>
        if(sessionStorage.getItem('teknisi_user')) {
            document.getElementById('sidebar-username').innerText = sessionStorage.getItem('teknisi_user');
        }

        function generateUniqueCode() {
            return Math.random().toString(36).substring(2, 8).toUpperCase();
        }

        setInterval(() => {
            if(!document.getElementById('panel-wo-instalasi').classList.contains('hidden')) {
                loadActiveOrders();
            } else if(!document.getElementById('panel-wo-maintenance').classList.contains('hidden')) {
                loadMaintenanceOrders();
            } else if(!document.getElementById('panel-wo-history').classList.contains('hidden')) {
                loadTechHistory();
            } else if(!document.getElementById('panel-stok').classList.contains('hidden')) {
                tkStockLoad(true);
            }
        }, 3000);


        /* ===== STOK BARANG (realtime, sinkron dengan admin.php & teknisi lain) ===== */
        let tkStockItems = [], tkStockHist = [], tkStockRev = '', tkStockLastHistId = null, tkStockBusy = false;

        function tkStockEsc(v) {
            return String(v == null ? '' : v).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        }
        function tkStockNum(n) {
            return (Number(n) || 0).toLocaleString('id-ID', { maximumFractionDigits: 2 });
        }
        function tkStockMe() {
            try { return sessionStorage.getItem('teknisi_user') || 'Teknisi'; } catch (e) { return 'Teknisi'; }
        }
        function tkStockSetLive(ok) {
            const el = document.getElementById('stok-live');
            if (!el) return;
            el.className = 'text-[11px] font-extrabold px-3 py-1.5 rounded-full flex items-center gap-1.5 whitespace-nowrap ' + (ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600');
            el.innerHTML = ok
                ? '<span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Realtime aktif'
                : '<span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Terputus, mencoba lagi...';
        }

        async function tkStockLoad(silent) {
            if (silent && (document.hidden || tkStockBusy)) return;
            try {
                const res = await fetch('admin.php?api=stock_list&rev=' + encodeURIComponent(silent ? tkStockRev : ''), { cache: 'no-store' });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.msg || 'Gagal memuat stok');
                tkStockSetLive(true);
                if (json.unchanged) return;
                tkStockRev = json.rev || '';
                tkStockItems = json.stock || [];
                tkStockHist = json.history || [];

                // Notifikasi bila ada transaksi baru dari Admin / teknisi lain
                const maxId = tkStockHist.reduce((m, h) => Math.max(m, Number(h.id) || 0), 0);
                if (tkStockLastHistId !== null && maxId > tkStockLastHistId) {
                    const me = tkStockMe();
                    const baru = tkStockHist.filter(h => Number(h.id) > tkStockLastHistId && (h.oleh || 'Admin') !== me);
                    if (baru.length) {
                        const h = baru[0];
                        showToast('📦 ' + (h.oleh || 'Admin') + ': ' + (h.tipe === 'MASUK' ? '+' : '-') + tkStockNum(h.jumlah) + ' ' + (h.sn || ''));
                    }
                }
                tkStockLastHistId = maxId;

                tkStockRenderTable();
                tkStockRenderHistory();
                tkStockRefreshSuggestions();
            } catch (e) {
                tkStockSetLive(false);
                if (!silent) showToast('❌ Gagal memuat stok: ' + e.message);
            }
        }

        function tkStockRefreshSuggestions() {
            const uniq = arr => [...new Set(arr.map(v => (v || '').trim()).filter(Boolean))];
            const fill = (id, vals) => { const dl = document.getElementById(id); if (dl) dl.innerHTML = vals.map(v => `<option value="${tkStockEsc(v)}">`).join(''); };
            fill('tstok-grup-list', uniq(tkStockItems.map(x => x.grup)));
            fill('tstok-brand-list', uniq(tkStockItems.map(x => x.brand)));
        }

        function tkStockRenderTable() {
            const body = document.getElementById('tstok-body');
            if (!body) return;
            const q = (document.getElementById('tstok-search').value || '').toLowerCase().trim();
            const rows = tkStockItems.filter(x => !q || [x.sn, x.grup, x.brand, x.satuan].join(' ').toLowerCase().includes(q));
            document.getElementById('tstok-count').innerText = rows.length + ' Data';
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-xs text-slate-400">Belum ada data stok.</td></tr>';
                return;
            }
            body.innerHTML = rows.map(x => `
                <tr class="hover:bg-slate-50/70">
                    <td class="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">${tkStockEsc(x.sn)}</td>
                    <td class="px-4 py-3">${tkStockEsc(x.grup) || '-'}</td>
                    <td class="px-4 py-3 text-right font-extrabold ${x.jumlah <= 0 ? 'text-rose-600' : 'text-slate-900'}">${tkStockNum(x.jumlah)}</td>
                    <td class="px-4 py-3">${tkStockEsc(x.satuan) || '-'}</td>
                    <td class="px-4 py-3">${tkStockEsc(x.brand) || '-'}</td>
                    <td class="px-4 py-3 text-center"><button type="button" data-sn="${tkStockEsc(x.sn)}" onclick="tkStockPrefill(this.dataset.sn)" title="Isi form dengan data ini" class="px-3 py-1.5 bg-sky-50 hover:bg-sky-100 text-sky-700 rounded-lg text-[11px] font-bold"><i class="fa-solid fa-pen"></i></button></td>
                </tr>`).join('');
        }

        function tkStockRenderHistory() {
            const body = document.getElementById('tstok-hist-body');
            if (!body) return;
            const q = (document.getElementById('tstok-hist-search').value || '').toLowerCase().trim();
            const f = (document.getElementById('tstok-hist-filter') || {}).value || '';
            const kindOf = o => { o = String(o || 'Admin'); return o.indexOf('INST-') === 0 ? 'INST' : (o.indexOf('MT-') === 0 ? 'MT' : 'MANUAL'); };
            const rows = tkStockHist.filter(x => (!f || kindOf(x.oleh) === f) && (!q || [x.sn, x.grup, x.brand, x.satuan, x.tipe, x.oleh].join(' ').toLowerCase().includes(q)));
            document.getElementById('tstok-hist-count').innerText = rows.length + ' Data';
            if (!rows.length) {
                body.innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-xs text-slate-400">Belum ada riwayat transaksi.</td></tr>';
                return;
            }
            const me = tkStockMe();
            body.innerHTML = rows.map(x => {
                const masuk = x.tipe === 'MASUK';
                const oleh = x.oleh || 'Admin';
                return `
                <tr class="hover:bg-slate-50/70">
                    <td class="px-4 py-3 text-xs text-slate-500 whitespace-nowrap">${tkStockEsc(x.created_at)}</td>
                    <td class="px-4 py-3"><span class="text-[10px] font-extrabold px-2.5 py-1 rounded-full ${masuk ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">${masuk ? 'MASUK' : 'KELUAR'}</span></td>
                    <td class="px-4 py-3 font-bold text-slate-900 whitespace-nowrap">${tkStockEsc(x.sn)}</td>
                    <td class="px-4 py-3">${tkStockEsc(x.grup) || '-'}</td>
                    <td class="px-4 py-3 text-right font-extrabold ${masuk ? 'text-emerald-600' : 'text-rose-600'}">${masuk ? '+' : '-'}${tkStockNum(x.jumlah)} <span class="text-[10px] font-bold text-slate-400">${tkStockEsc(x.satuan)}</span></td>
                    <td class="px-4 py-3">${tkStockEsc(x.brand) || '-'}</td>
                    <td class="px-4 py-3 text-xs font-bold whitespace-nowrap">${(() => { const k = kindOf(oleh); return k === 'MANUAL' ? `<span class="${oleh === me ? 'text-emerald-700' : (oleh === 'Admin' ? 'text-slate-500' : 'text-sky-700')}">${tkStockEsc(oleh)}${oleh === me ? ' (Anda)' : ''}</span>` : `<span class="text-[10px] font-extrabold px-2.5 py-1 rounded-full ${k === 'INST' ? 'bg-sky-100 text-sky-700' : 'bg-rose-100 text-rose-700'}">${tkStockEsc(oleh)}</span>`; })()}</td>
                </tr>`;
            }).join('');
        }

        function tkStockPrefill(sn) {
            const it = tkStockItems.find(x => x.sn === sn);
            if (!it) return;
            document.getElementById('tstok-sn').value = it.sn;
            document.getElementById('tstok-grup').value = it.grup || '';
            document.getElementById('tstok-satuan').value = it.satuan || '';
            document.getElementById('tstok-brand').value = it.brand || '';
            document.getElementById('tstok-jumlah').value = '';
            document.getElementById('tstok-jumlah').focus();
            document.getElementById('tstok-sn').scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        function tkStockReset() {
            ['tstok-sn', 'tstok-grup', 'tstok-jumlah', 'tstok-satuan', 'tstok-brand'].forEach(id => { document.getElementById(id).value = ''; });
            document.getElementById('tstok-tipe').value = 'MASUK';
        }

        async function tkStockSave() {
            const payload = {
                sn: document.getElementById('tstok-sn').value.trim(),
                grup: document.getElementById('tstok-grup').value.trim(),
                jumlah: parseFloat(document.getElementById('tstok-jumlah').value),
                satuan: document.getElementById('tstok-satuan').value.trim(),
                brand: document.getElementById('tstok-brand').value.trim(),
                tipe: document.getElementById('tstok-tipe').value,
                oleh: tkStockMe()
            };
            if (!payload.sn) { showToast('SN wajib diisi'); return; }
            if (!(payload.jumlah > 0)) { showToast('Jumlah harus lebih dari 0'); return; }
            const btn = document.getElementById('tstok-save-btn');
            btn.disabled = true; tkStockBusy = true;
            try {
                const res = await fetch('admin.php?api=stock_save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const json = await res.json();
                if (json.success) {
                    showToast('✅ Transaksi stok tersimpan');
                    tkStockReset();
                    tkStockBusy = false;
                    await tkStockLoad(false);
                } else {
                    showToast('❌ ' + (json.msg || 'Gagal menyimpan'));
                }
            } catch (e) {
                showToast('❌ Gagal menyimpan: ' + e.message);
            } finally {
                btn.disabled = false; tkStockBusy = false;
            }
        }

        function showToast(message) {
            const container = document.getElementById('toast-container');
            if(!container) return;
            const toast = document.createElement('div');
            toast.className = `glass-dark text-white px-5 py-3.5 rounded-2xl shadow-2xl flex items-center gap-4 border border-white/10 w-full md:w-auto pointer-events-auto`;
            toast.innerHTML = `<div class="text-xs font-bold"><i class="fa-solid fa-circle-check text-emerald-400 mr-2"></i>${message}</div>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function toggleSidebar() {
            let sidebar = document.getElementById('sidebar');
            let overlay = document.getElementById('sidebar-overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }

        function toggleWorkOrderDropdown() {
            let submenu = document.getElementById('wo-submenu');
            let chevron = document.getElementById('wo-chevron');
            if (submenu.style.display === 'none' || submenu.classList.contains('hidden')) {
                submenu.classList.remove('hidden');
                submenu.style.display = 'block';
                chevron.style.transform = 'rotate(0deg)';
            } else {
                submenu.classList.add('hidden');
                submenu.style.display = 'none';
                chevron.style.transform = 'rotate(-90deg)';
            }
        }

        function logoutTeknisi() {
            try { sessionStorage.removeItem('teknisi_logged'); sessionStorage.removeItem('teknisi_user'); } catch(e) {}
            window.location.replace('login_teknisi.php');
        }

        function switchMenu(menu) {
            ['panel-wo-instalasi', 'panel-wo-maintenance', 'panel-wo-history', 'panel-stok', 'panel-placeholder'].forEach(id => {
                document.getElementById(id).classList.add('hidden');
            });

            ['wo-instalasi', 'wo-maintenance', 'wo-history'].forEach(m => {
                let el = document.getElementById('menu-' + m);
                if(el) el.className = 'block px-3 py-2 rounded-lg hover:bg-slate-100 text-slate-600 text-[11px]';
            });

            // Tombol Stok Barang (di luar submenu WO)
            let stokBtn = document.getElementById('menu-stok');
            if(stokBtn) stokBtn.className = (menu === 'stok')
                ? 'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl bg-sky-600 text-white shadow-sm transition-all focus:outline-none'
                : 'w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-slate-100 text-slate-600 transition-all focus:outline-none';

            let activeMenuEl = (menu === 'stok') ? null : document.getElementById('menu-' + menu);
            if(activeMenuEl) {
                activeMenuEl.className = 'block px-3 py-2 rounded-lg bg-sky-50 text-sky-700 font-extrabold text-[11px]';
            }

            if(menu === 'wo-instalasi') {
                document.getElementById('panel-wo-instalasi').classList.remove('hidden');
                document.getElementById('header-title').innerText = "Work Order Instalasi";
                document.getElementById('breadcrumb').innerText = "Home / Work Order Instalasi";
                loadActiveOrders();
            } else if(menu === 'wo-maintenance') {
                document.getElementById('panel-wo-maintenance').classList.remove('hidden');
                document.getElementById('header-title').innerText = "Work Order Maintenance";
                document.getElementById('breadcrumb').innerText = "Home / Work Order Maintenance";
                loadMaintenanceOrders();
            } else if(menu === 'wo-history') {
                document.getElementById('panel-wo-history').classList.remove('hidden');
                document.getElementById('header-title').innerText = "Work Order History";
                document.getElementById('breadcrumb').innerText = "Home / Work Order History";
                loadTechHistory();
            } else if(menu === 'stok') {
                document.getElementById('panel-stok').classList.remove('hidden');
                document.getElementById('header-title').innerText = "Stok Barang";
                document.getElementById('breadcrumb').innerText = "Home / Stok Barang";
                tkStockLoad(false);
            }

            if(window.innerWidth < 768) {
                toggleSidebar();
            }
        }

        // FUNGSI UNTUK MENAMBAHKAN GRUP SN PERANGKAT + FOTO PERANGKAT INLINE (+)
        // Foto tidak wajib di HTML agar HP tidak memblokir submit jika kamera/galeri gagal
        function addDeviceWithPhoto(containerId, labelName, textInputName, fileInputName, placeholderText, themeColor = 'sky', isRequired = true) {
            let container = document.getElementById(containerId);
            let index = container.children.length + 1;
            let card = document.createElement('div');
            
            let bgTheme = themeColor === 'sky' ? 'bg-slate-50 border-slate-200' : 'bg-rose-50/40 border-rose-100';
            let focusTheme = themeColor === 'sky' ? 'focus:border-sky-500' : 'focus:border-rose-500';
            let fileBgTheme = themeColor === 'sky' ? 'file:bg-sky-100 file:text-sky-700 hover:file:bg-sky-200' : 'file:bg-rose-100 file:text-rose-700 hover:file:bg-rose-200';
            let reqAttr = isRequired ? 'required' : '';

            card.className = `p-3 rounded-2xl border ${bgTheme} space-y-2 relative`;
            card.innerHTML = `
                <div class="flex justify-between items-center">
                    <span class="font-extrabold text-[11px] text-slate-700">SN ${labelName} #${index}</span>
                    ${index > 1 || !isRequired ? `<button type="button" onclick="this.closest('.p-3').remove()" class="text-rose-600 hover:text-rose-800 text-[11px] font-bold flex items-center gap-1"><i class="fa-solid fa-trash"></i> Hapus</button>` : ''}
                </div>
                <div>
                    <input type="text" name="${textInputName}" placeholder="${placeholderText}" ${reqAttr} class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-mono font-bold focus:outline-none ${focusTheme}">
                </div>
                <div>
                    <label class="block font-bold text-slate-500 text-[10px] uppercase mb-1"><i class="fa-solid fa-camera mr-1"></i> Foto SN ${labelName} #${index} <span class="normal-case font-medium text-slate-400">(kamera/galeri)</span></label>
                    <input type="file" name="${fileInputName}" accept="image/*" capture="environment" class="w-full text-xs file:mr-2 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:font-bold ${fileBgTheme} cursor-pointer border rounded-xl bg-white">
                </div>
                <div>
                    <label class="block font-bold text-slate-500 text-[10px] uppercase mb-1"><i class="fa-solid fa-comment-dots mr-1"></i> Deskripsi ${labelName} #${index} <span class="normal-case font-medium text-slate-400">(opsional)</span></label>
                    <textarea name="${textInputName}-desc" rows="2" maxlength="500" placeholder="Cth: lokasi pemasangan, kondisi unit, catatan lain..." class="w-full px-3 py-2 bg-white border border-slate-300 rounded-xl font-medium focus:outline-none ${focusTheme} resize-none"></textarea>
                </div>
            `;
            container.appendChild(card);
        }

        async function loadActiveOrders() {
            try {
                let res = await fetch('admin.php?api=get_data', { cache: 'no-store' });
                let json = await res.json();
                let container = document.getElementById('active-orders-container');
                let acceptedWOs = JSON.parse(localStorage.getItem('tech_accepted_wos') || '{}');

                // Ambil status WO dari server (setelah form diisi / di-approve)
                let techStatusMap = {};
                try {
                    let resTech = await fetch('admin.php?api=get_technician_orders', { cache: 'no-store' });
                    let jsonTech = await resTech.json();
                    if (jsonTech.success && jsonTech.data) {
                        jsonTech.data.forEach(to => {
                            if (to.id) techStatusMap[to.id] = (to.status || '').toUpperCase();
                        });
                    }
                } catch(e2) {}

                if(json.success && json.data.orders && json.data.orders.length > 0) {
                    container.innerHTML = json.data.orders.map(o => {
                        let isAccepted = acceptedWOs[o.id] === true;
                        let techStatus = techStatusMap[o.id] || '';

                        let ribbonText = o.trackingStatus || 'PROSES';
                        let ribbonStyle = '';
                        let actionButton = '';

                        if (techStatus === 'MENUNGGU KONFIRMASI') {
                            // Sudah isi form → tunggu admin tekan APPROVE
                            ribbonText = 'MENUNGGU APPROVE';
                            ribbonStyle = 'background-color: #d97706;'; // amber
                            actionButton = `<div class="w-full py-2.5 bg-amber-50 text-amber-700 border border-amber-200 rounded-xl text-xs font-extrabold text-center"><i class="fa-solid fa-hourglass-half mr-1"></i> Menunggu Approve Admin</div>`;
                        } else if (techStatus === 'DATA APPROVE') {
                            // Admin sudah tekan tombol APPROVE
                            ribbonText = 'DATA APPROVE';
                            ribbonStyle = 'background-color: #059669;'; // hijau
                            actionButton = `<div class="w-full py-2.5 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-xl text-xs font-extrabold text-center"><i class="fa-solid fa-circle-check mr-1"></i> Data Approve</div>`;
                        } else if (isAccepted) {
                            ribbonText = 'DITERIMA';
                            actionButton = `<button onclick="openFormModal('${o.id}', '${o.name}')" class="w-full py-2.5 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-extrabold shadow-md transition-all flex items-center justify-center gap-1.5"><i class="fa-solid fa-pen-to-square"></i> ISI FORM</button>`;
                        } else {
                            actionButton = `<button onclick="terimaWorkOrder('${o.id}')" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-extrabold shadow-md transition-all flex items-center justify-center gap-1.5"><i class="fa-solid fa-circle-check"></i> TERIMA WO</button>`;
                        }

                        return `
                            <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-sm flex flex-col justify-between gap-4 relative overflow-hidden">
                                <div class="ribbon-wrap"><div class="ribbon" style="${ribbonStyle}">${ribbonText}</div></div>
                                <div>
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-xs font-black bg-sky-100 text-sky-700 px-2.5 py-1 rounded-xl">${o.id}</span>
                                        <span class="text-[10px] text-slate-400 font-bold">${o.date}</span>
                                    </div>
                                    <div class="mb-4 pr-12">
                                        <h4 class="font-black text-slate-900 text-base mb-1 flex items-center gap-2"><i class="fa-solid fa-user-circle text-sky-600"></i> ${o.name}</h4>
                                        <div class="text-xs text-slate-600 font-medium space-y-1">
                                            <div><i class="fa-solid fa-phone mr-1.5 text-slate-400"></i> ${o.phone}</div>
                                            <div class="line-clamp-2"><i class="fa-solid fa-location-dot mr-1.5 text-slate-400"></i> ${o.address}</div>
                                        </div>
                                    </div>
                                </div>
                                ${actionButton}
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<div class="col-span-full text-center py-12 text-slate-400 font-bold bg-white rounded-3xl border border-slate-200">Belum ada pesanan / tugas lapangan aktif dari admin.</div>';
                }
            } catch(e) {}
        }

        async function loadMaintenanceOrders() {
            try {
                let res = await fetch('admin.php?api=get_tickets', { cache: 'no-store' });
                let json = await res.json();
                let container = document.getElementById('maintenance-orders-container');
                let acceptedMaint = JSON.parse(localStorage.getItem('tech_accepted_maint') || '{}');

                if(json.success && json.data && json.data.length > 0) {
                    container.innerHTML = json.data.map(t => {
                        let isAccepted = acceptedMaint[t.id] === true;
                        let actionBtn = isAccepted ?
                            `<button onclick="openFormModalMaint('${t.id}', '${t.nama}', '${t.customer_id}')" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-extrabold shadow-md transition-all flex items-center justify-center gap-1.5"><i class="fa-solid fa-pen-to-square"></i> ISI FORM MAINTENANCE</button>` :
                            `<button onclick="terimaMaintenanceTask('${t.id}')" class="w-full py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-extrabold shadow-md transition-all flex items-center justify-center gap-1.5"><i class="fa-solid fa-circle-check"></i> TEKNISI TERIMA TUGAS</button>`;

                        return `
                            <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-sm flex flex-col justify-between gap-4 relative overflow-hidden">
                                <div class="ribbon-wrap"><div class="ribbon" style="background-color: #e11d48;">${isAccepted ? 'DITERIMA' : 'KENDALA'}</div></div>
                                <div>
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="text-xs font-black bg-rose-100 text-rose-800 px-2.5 py-1 rounded-xl border border-rose-200"><i class="fa-solid fa-screwdriver-wrench mr-1"></i> W.O. MAINTENANCE</span>
                                        <span class="text-[10px] text-slate-400 font-bold">${t.created_at}</span>
                                    </div>
                                    <div class="mb-4 pr-12">
                                        <h4 class="font-black text-slate-900 text-base mb-1 flex items-center gap-2"><i class="fa-solid fa-triangle-exclamation text-amber-500"></i> ${t.judul}</h4>
                                        <div class="text-xs text-slate-600 font-medium space-y-1">
                                            <div><i class="fa-solid fa-user mr-1.5 text-slate-400"></i> Pelanggan: <b>${t.nama}</b></div>
                                            <div><i class="fa-solid fa-circle-exclamation mr-1.5 text-slate-400"></i> Tingkat: <span class="font-bold text-rose-600">${t.tingkat_masalah}</span></div>
                                            <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-[11px] text-slate-700 mt-2"><b>Deskripsi Kendala:</b> ${t.deskripsi}</div>
                                        </div>
                                    </div>
                                </div>
                                ${actionBtn}
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<div class="col-span-full text-center py-12 text-slate-400 font-bold bg-white rounded-3xl border border-slate-200">Belum ada laporan kendala / maintenance dari pelanggan.</div>';
                }
            } catch(e) {}
        }

        function terimaMaintenanceTask(ticketId) {
            let acceptedMaint = JSON.parse(localStorage.getItem('tech_accepted_maint') || '{}');
            acceptedMaint[ticketId] = true;
            localStorage.setItem('tech_accepted_maint', JSON.stringify(acceptedMaint));
            
            fetch('admin.php?api=update_ticket_status', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ticket_id: ticketId, status: 'PROSES'})
            });

            showToast('Tugas Maintenance Berhasil Diterima! Silahkan isi Form.');
            loadMaintenanceOrders();
        }

        function terimaWorkOrder(orderId) {
            let acceptedWOs = JSON.parse(localStorage.getItem('tech_accepted_wos') || '{}');
            acceptedWOs[orderId] = true;
            localStorage.setItem('tech_accepted_wos', JSON.stringify(acceptedWOs));
            showToast('Work Order Instalasi Berhasil Diterima!');
            loadActiveOrders();
        }

        function openFormModal(invoiceId, customerName) {
            document.getElementById('modal-invoice-id').value = invoiceId;
            document.getElementById('modal-cust-id').value = invoiceId;
            document.getElementById('modal-cust-name').value = customerName;
            
            document.getElementById('dvr-container').innerHTML = '';
            document.getElementById('cctv-container').innerHTML = '';
            document.getElementById('hdd-container').innerHTML = '';
            document.getElementById('other-container').innerHTML = '';

            addDeviceWithPhoto('dvr-container', 'DVR/NVR', 't-dvr', 't-foto-dvr', 'Cth: DVR-983472', 'sky', true);
            addDeviceWithPhoto('cctv-container', 'CCTV', 't-cctv', 't-foto-cctv', 'Cth: CAM-552140', 'sky', true);
            addDeviceWithPhoto('hdd-container', 'Harddisk', 't-hdd', 't-foto-hdd', 'Cth: WDC-TB1-8832', 'sky', true);
            addDeviceWithPhoto('other-container', 'Perangkat Lain', 't-other', 't-foto-other', 'Cth: PSU-12V-2231', 'sky', false);

            document.getElementById('t-kabel').value = '';
            document.getElementById('t-kabel-desc').value = '';
            document.getElementById('form-modal').style.display = 'flex';
        }

        function closeFormModal() {
            document.getElementById('form-modal').style.display = 'none';
        }

        function openFormModalMaint(ticketId, nama, customerId) {
            document.getElementById('maint-modal-ticket-id').value = ticketId;
            document.getElementById('maint-modal-cust-id').value = customerId || ticketId;
            document.getElementById('maint-modal-cust-name').value = `${nama} (${ticketId})`;

            document.getElementById('maint-dvr-container').innerHTML = '';
            document.getElementById('maint-cctv-container').innerHTML = '';
            document.getElementById('maint-hdd-container').innerHTML = '';
            document.getElementById('maint-other-container').innerHTML = '';

            addDeviceWithPhoto('maint-dvr-container', 'DVR/NVR', 'm-dvr', 'm-foto-dvr', 'Cth: DVR-983472', 'rose', false);
            addDeviceWithPhoto('maint-cctv-container', 'CCTV', 'm-cctv', 'm-foto-cctv', 'Cth: CAM-552140', 'rose', false);
            addDeviceWithPhoto('maint-hdd-container', 'Harddisk', 'm-hdd', 'm-foto-hdd', 'Cth: WDC-TB1-8832', 'rose', false);
            addDeviceWithPhoto('maint-other-container', 'Perangkat Lain', 'm-other', 'm-foto-other', 'Cth: PSU-12V-2231', 'rose', false);

            document.getElementById('m-deskripsi').value = '';
            document.getElementById('form-modal-maint').style.display = 'flex';
        }

        function closeFormModalMaint() {
            document.getElementById('form-modal-maint').style.display = 'none';
        }

        // Kompresi sangat agresif untuk HP (hindari gagal upload / ERROR di jaringan luar)
        function compressImage(file, maxWidth = 220, quality = 0.22) {
            return new Promise((resolve) => {
                if (!file) return resolve('');
                try {
                    // Tolak file ekstrem; biarkan SN tetap tersimpan
                    if (file.size > 15 * 1024 * 1024) return resolve('');
                    let reader = new FileReader();
                    reader.onerror = () => resolve('');
                    reader.onload = function(event) {
                        let raw = event.target.result;
                        if (typeof raw !== 'string') return resolve('');
                        let img = new Image();
                        let done = false;
                        let finish = (val) => { if (!done) { done = true; resolve(val || ''); } };
                        // Timeout 8 detik agar HP tidak menggantung
                        setTimeout(() => finish(''), 8000);
                        img.onload = function() {
                            try {
                                let canvas = document.createElement('canvas');
                                let width = img.naturalWidth || img.width || 280;
                                let height = img.naturalHeight || img.height || 280;
                                if (width > maxWidth) {
                                    height = Math.round((height * maxWidth) / width);
                                    width = maxWidth;
                                }
                                if (height > 400) {
                                    width = Math.round((width * 400) / height);
                                    height = 400;
                                }
                                canvas.width = Math.max(1, width);
                                canvas.height = Math.max(1, height);
                                let ctx = canvas.getContext('2d');
                                ctx.fillStyle = '#ffffff';
                                ctx.fillRect(0, 0, canvas.width, canvas.height);
                                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                                let out = canvas.toDataURL('image/jpeg', quality);
                                if (out.length > 80000) out = canvas.toDataURL('image/jpeg', 0.15);
                                if (out.length > 80000) out = canvas.toDataURL('image/jpeg', 0.1);
                                finish(out);
                            } catch (e) {
                                finish('');
                            }
                        };
                        img.onerror = function() {
                            // HEIC/format tidak didukung browser → kosong (jangan throw)
                            finish('');
                        };
                        img.src = raw;
                    };
                    reader.readAsDataURL(file);
                } catch (e) {
                    resolve('');
                }
            });
        }

        // SN + foto + deskripsi sejajar; tidak pernah melempar error
        // strict=true (Perangkat Lain): foto/deskripsi wajib disertai SN
        async function collectDeviceData(textName, fileName, strict = false) {
            let textEls = Array.from(document.querySelectorAll(`input[name="${textName}"]`));
            let fileEls = Array.from(document.querySelectorAll(`input[name="${fileName}"]`));
            let descEls = Array.from(document.querySelectorAll(`textarea[name="${textName}-desc"]`));
            let sns = [];
            let photos = [];
            let units = [];   // [{sn, desc, p}] sejajar dengan photos
            let invalid = []; // nomor unit yang punya foto/deskripsi tetapi SN kosong
            for (let i = 0; i < textEls.length; i++) {
                let sn = (textEls[i].value || '').trim();
                let desc = ((descEls[i] && descEls[i].value) || '').trim();
                let photo = '';
                let fileEl = fileEls[i];
                if (fileEl && fileEl.files && fileEl.files[0]) {
                    photo = await compressImage(fileEl.files[0]);
                }
                let hasPhoto = !!(photo && photo.indexOf('data:image/') === 0);
                if (sn !== '') {
                    sns.push(sn);
                    photos.push(photo || '');
                    units.push({ sn: sn, desc: desc, p: hasPhoto ? 1 : 0 });
                } else if (hasPhoto && !strict) {
                    sns.push('-');
                    photos.push(photo);
                    units.push({ sn: '-', desc: desc, p: 1 });
                } else if (desc !== '' || hasPhoto) {
                    invalid.push(i + 1);
                }
            }
            return {
                snText: sns.filter(s => s && s !== '-').join(', ') || sns.join(', '),
                photos: photos,
                units: units,
                invalid: invalid,
                firstPhoto: photos.find(p => p && p.indexOf('data:image/') === 0) || '',
                hasAnyPhoto: photos.some(p => p && p.indexOf('data:image/') === 0)
            };
        }

        // Cek unit yang punya foto/deskripsi tapi SN kosong. Return true jika semua valid.
        function checkInvalidUnits(list) {
            let msgs = [];
            list.forEach(([label, d]) => {
                if (d && d.invalid && d.invalid.length) msgs.push(label + ' #' + d.invalid.join(', #'));
            });
            if (msgs.length) {
                alert('Isi Serial Number terlebih dahulu untuk: ' + msgs.join('; ') + '.\n(Deskripsi/foto harus disertai SN agar bisa diterima Admin & User.)');
                return false;
            }
            return true;
        }

        // Deskripsi per unit dikirim ke server sebagai JSON (sejajar dengan urutan foto)
        function buildNotesJson(dvr, cctv, hdd, other, kabelDesc) {
            let pick = d => (d && d.units) ? d.units : [];
            return JSON.stringify({ v: 2, dvr: pick(dvr), cctv: pick(cctv), hdd: pick(hdd), other: pick(other), kabel: kabelDesc || '' });
        }

        // Encode array foto jadi string yang diterima admin (JSON array data-URI)
        function photosToDbValue(photos) {
            let arr = (photos || []).map(p => (p && p.indexOf('data:image/') === 0) ? p : '');
            if (!arr.some(p => p)) return '';
            return JSON.stringify(arr);
        }

        // Cadangan: kirim foto satu per satu jika payload utama tanpa foto
        async function uploadTechPhotos(woId, dvrData, cctvData, hddData, mainPhoto, otherData) {
            let jobs = [];
            if (mainPhoto && mainPhoto.indexOf('data:image/') === 0) {
                jobs.push({ field: 'main', index: 0, photo: mainPhoto });
            }
            [['dvr', dvrData], ['cctv', cctvData], ['hdd', hddData], ['other', otherData]].forEach(([field, data]) => {
                if (!data) return;
                (data.photos || []).forEach((photo, index) => {
                    if (photo && photo.indexOf('data:image/') === 0) {
                        jobs.push({ field, index, photo });
                    }
                });
            });
            let failed = 0;
            for (let job of jobs) {
                let ok = false;
                for (let attempt = 0; attempt < 2 && !ok; attempt++) {
                    try {
                        let controller = new AbortController();
                        let timer = setTimeout(() => controller.abort(), 60000);
                        let res = await fetch('admin.php?api=save_technician_photo', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: woId, field: job.field, index: job.index, photo: job.photo }),
                            signal: controller.signal
                        });
                        clearTimeout(timer);
                        let json = await res.json();
                        if (json && json.success) ok = true;
                    } catch (err) {}
                }
                if (!ok) failed++;
            }
            return failed;
        }

        async function submitInstalasiTeknisi(e) {
            e.preventDefault();
            let customerId = document.getElementById('modal-cust-id').value;
            let invoiceId = document.getElementById('modal-invoice-id').value;
            let customerName = document.getElementById('modal-cust-name').value;
            let submitBtn = document.getElementById('submit-btn');

            submitBtn.disabled = true;
            submitBtn.innerText = 'Memproses & Mengompres Foto Perangkat...';

            try {
                let dvrData = await collectDeviceData('t-dvr', 't-foto-dvr');
                let cctvData = await collectDeviceData('t-cctv', 't-foto-cctv');
                let hddData = await collectDeviceData('t-hdd', 't-foto-hdd');
                let otherData = await collectDeviceData('t-other', 't-foto-other', true);
                let dvrValues = dvrData.snText;
                let cctvValues = cctvData.snText;
                let hddValues = hddData.snText;

                if (!checkInvalidUnits([['DVR/NVR', dvrData], ['CCTV', cctvData], ['Harddisk', hddData], ['Perangkat Lain', otherData]])) return;
                if (!dvrValues && !cctvValues && !hddValues && !otherData.snText) {
                    alert('Minimal isi satu Serial Number perangkat.');
                    return;
                }

                let primaryProofImage = (dvrData.firstPhoto || cctvData.firstPhoto || hddData.firstPhoto || '');
                let inputKabel = parseInt(document.getElementById('t-kabel').value) || 0;

                // Payload TANPA foto — hindari body terlalu besar (post_max_size) di HP
                let payload = {
                    id: invoiceId || ('WO-INST-' + generateUniqueCode()),
                    customer_id: customerId ? customerId : 'CUST-' + generateUniqueCode(),
                    nama: customerName ? customerName : 'Pelanggan',
                    dvr_sn: dvrValues,
                    cctv_sn: cctvValues,
                    hdd_sn: hddValues,
                    other_sn: otherData.snText,
                    notes_json: buildNotesJson(dvrData, cctvData, hddData, otherData, ((document.getElementById('t-kabel-desc') || {}).value || '').trim()),
                    jumlah_kabel: inputKabel,
                    kabel_qty: inputKabel,
                    proof_image: '',
                    proof_image_dvr: '',
                    proof_image_cctv: '',
                    proof_image_hdd: '',
                    wo_type: 'INSTALASI',
                    catatan_maintenance: 'Instalasi perangkat CCTV rampung.'
                };

                submitBtn.innerText = 'Menyimpan data SN...';
                let res = await fetch('admin.php?api=save_technician_order', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                let rawText = await res.text();
                let json;
                try {
                    json = JSON.parse(rawText);
                } catch (err) {
                    throw new Error('Respon server tidak valid saat simpan SN.');
                }
                
                if (json && json.success) {
                    submitBtn.innerText = 'Mengunggah foto satu per satu...';
                    let failedPhotos = await uploadTechPhotos(payload.id, dvrData, cctvData, hddData, primaryProofImage, otherData);
                    if (failedPhotos > 0) {
                        showToast(failedPhotos + ' foto gagal. SN tetap tersimpan. Pakai JPG lebih kecil.');
                    }

                    let currentDate = new Date().toISOString().split('T')[0];
                    let baseName = (payload.nama || 'user').toLowerCase().replace(/[^a-z0-9]/g, '').substring(0, 10) || 'user';
                    await fetch('admin.php?api=save_customer_account', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            customer_id: payload.customer_id,
                            name: payload.nama,
                            username: baseName + '_' + Math.floor(Math.random() * 100),
                            password: 'CCTV123',
                            dvr_sn: dvrValues,
                            cctv_sn: cctvValues,
                            hdd_sn: hddValues,
                            other_sn: otherData.snText,
                            active_date: currentDate
                        })
                    });

                    showToast('Work Order Instalasi ('+ payload.id +') Berhasil Disimpan!');
                    closeFormModal();
                    switchMenu('wo-history');
                } else {
                    alert('Gagal dari server: ' + (json.msg || 'Unknown error'));
                }
            } catch(err) {
                console.error(err);
                alert('Gagal mengirim form instalasi: ' + (err.message || 'Koneksi/jaringan atau ukuran foto. Coba foto lebih kecil.'));
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-circle-check mr-1"></i> Selesaikan & Kirim ke Admin & User';
            }
        }

        async function submitMaintenanceTeknisi(e) {
            e.preventDefault();
            let ticketId = document.getElementById('maint-modal-ticket-id').value;
            let customerId = document.getElementById('maint-modal-cust-id').value;
            let customerName = document.getElementById('maint-modal-cust-name').value;
            let deskripsiPerbaikan = document.getElementById('m-deskripsi').value.trim();
            let submitBtn = document.getElementById('maint-submit-btn');

            submitBtn.disabled = true;
            submitBtn.innerText = 'Mengompres Foto & Mengirim...';

            try {
                let dvrData = await collectDeviceData('m-dvr', 'm-foto-dvr');
                let cctvData = await collectDeviceData('m-cctv', 'm-foto-cctv');
                let hddData = await collectDeviceData('m-hdd', 'm-foto-hdd');
                let otherData = await collectDeviceData('m-other', 'm-foto-other', true);
                if (!checkInvalidUnits([['DVR/NVR', dvrData], ['CCTV', cctvData], ['Harddisk', hddData], ['Perangkat Lain', otherData]])) return;
                let dvrValues = dvrData.snText;
                let cctvValues = cctvData.snText;
                let hddValues = hddData.snText;

                let primaryProofImage = (dvrData.firstPhoto || cctvData.firstPhoto || hddData.firstPhoto || '');

                // Payload TANPA foto
                let payload = {
                    id: ticketId || ('WO-MT-' + generateUniqueCode()),
                    customer_id: customerId,
                    nama: customerName,
                    dvr_sn: dvrValues,
                    cctv_sn: cctvValues,
                    hdd_sn: hddValues,
                    other_sn: otherData.snText,
                    notes_json: buildNotesJson(dvrData, cctvData, hddData, otherData, ''),
                    catatan_maintenance: deskripsiPerbaikan,
                    proof_image: '',
                    proof_image_dvr: '',
                    proof_image_cctv: '',
                    proof_image_hdd: '',
                    wo_type: 'MAINTENANCE'
                };

                submitBtn.innerText = 'Menyimpan data SN...';
                let res = await fetch('admin.php?api=save_technician_order', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload)
                });
                let rawText = await res.text();
                let json;
                try {
                    json = JSON.parse(rawText);
                } catch (err) {
                    throw new Error('Respon server tidak valid saat simpan SN.');
                }

                if (json && json.success) {
                    submitBtn.innerText = 'Mengunggah foto satu per satu...';
                    let failedPhotos = await uploadTechPhotos(payload.id, dvrData, cctvData, hddData, primaryProofImage, otherData);
                    if (failedPhotos > 0) {
                        showToast(failedPhotos + ' foto gagal. SN tetap tersimpan.');
                    }

                    await fetch('admin.php?api=update_ticket_status', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ticket_id: ticketId, status: 'SELESAI'})
                    });

                    let currentDate = new Date().toISOString().split('T')[0];
                    let custAccBody = {
                        customer_id: customerId,
                        name: customerName,
                        active_date: currentDate
                    };
                    if (dvrValues) custAccBody.dvr_sn = dvrValues;
                    if (cctvValues) custAccBody.cctv_sn = cctvValues;
                    if (hddValues) custAccBody.hdd_sn = hddValues;
                    if (otherData.snText) custAccBody.other_sn = otherData.snText;

                    await fetch('admin.php?api=save_customer_account', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify(custAccBody)
                    });

                    showToast('Laporan Maintenance & Foto Perangkat Berhasil Diteruskan ke Admin & Member!');
                    closeFormModalMaint();
                    switchMenu('wo-history');
                } else {
                    alert('Gagal maintenance: ' + (json.msg || 'Unknown error'));
                }
            } catch(err) {
                console.error(err);
                alert('Gagal mengirim laporan maintenance: ' + (err.message || 'Koneksi/jaringan atau ukuran foto. Coba foto lebih kecil.'));
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1"></i> Kirim Laporan & Sinkron ke Member & Admin';
            }
        }

        function tEsc(s) {
            return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        function tParse(raw, fallback) {
            if (!raw) return fallback;
            if (typeof raw === 'object') return raw;
            try { let j = JSON.parse(raw); return (j && typeof j === 'object') ? j : fallback; } catch (e) { return fallback; }
        }

        // Lightbox foto sederhana (data-URI tidak bisa dibuka lewat target=_blank di sebagian browser)
        function openPhotoPreview(src) {
            if (!src) return;
            let ov = document.createElement('div');
            ov.className = 'fixed inset-0 z-[120] bg-slate-950/85 flex items-center justify-center p-4';
            ov.onclick = () => ov.remove();
            let img = document.createElement('img');
            img.src = src;
            img.className = 'max-w-full max-h-full rounded-2xl shadow-2xl';
            ov.appendChild(img);
            document.body.appendChild(ov);
        }

        function renderTechStatusBadge(to) {
            let st = (to.status || '').toUpperCase();
            if ((to.wo_type || '').toUpperCase() === 'MAINTENANCE') return '';
            if (st === 'MENUNGGU KONFIRMASI') return '<span class="text-[10px] bg-amber-100 text-amber-700 font-extrabold px-2.5 py-1 rounded-full border border-amber-200"><i class="fa-solid fa-hourglass-half mr-1"></i> MENUNGGU APPROVE</span>';
            if (st === 'DATA APPROVE') return '<span class="text-[10px] bg-emerald-100 text-emerald-700 font-extrabold px-2.5 py-1 rounded-full border border-emerald-200"><i class="fa-solid fa-circle-check mr-1"></i> DATA APPROVE</span>';
            return '';
        }

        // Ringkasan SN + deskripsi + foto per unit, sama dengan yang diterima Admin & User
        function renderTechDevices(to) {
            let notes = tParse(to.notes_json, {});
            let groups = [['dvr', 'DVR/NVR', 'fa-server', to.dvr_sn, to.proof_image_dvr], ['cctv', 'CCTV', 'fa-video', to.cctv_sn, to.proof_image_cctv], ['hdd', 'Harddisk', 'fa-hard-drive', to.hdd_sn, to.proof_image_hdd], ['other', 'Perangkat Lain', 'fa-plug', to.other_sn, to.proof_image_other]];
            let html = '';
            groups.forEach(([key, label, icon, snRaw, photoRaw]) => {
                let units = Array.isArray(notes[key]) ? notes[key].filter(u => u && typeof u === 'object') : [];
                if (!units.length) {
                    units = String(snRaw || '').split(',').map(s => s.trim()).filter(Boolean).map(s => ({ sn: s, desc: '' }));
                }
                if (!units.length) return;
                let photos = tParse(photoRaw, []);
                if (!Array.isArray(photos)) photos = [];
                html += `<div class="mt-2"><div class="text-[10px] font-extrabold text-slate-500 uppercase mb-1"><i class="fa-solid ${icon} text-sky-600 mr-1"></i>${label}</div>` + units.map((u, i) => {
                    let sn = (u.sn && u.sn !== '-') ? u.sn : '(tanpa SN)';
                    let ph = (typeof photos[i] === 'string' && photos[i].indexOf('data:image/') === 0) ? photos[i] : '';
                    return `<div class="flex gap-2 items-start bg-white border border-slate-100 rounded-lg p-1.5 mb-1">
                        ${ph ? `<img src="${ph}" onclick="openPhotoPreview(this.src)" class="w-10 h-10 object-cover rounded-md border border-slate-200 cursor-pointer shrink-0">` : ''}
                        <div class="min-w-0"><div class="font-mono font-bold text-[11px] text-sky-700 break-all">${tEsc(sn)}</div>${u.desc ? `<div class="text-[10px] text-slate-600 whitespace-pre-line">${tEsc(u.desc)}</div>` : ''}</div>
                    </div>`;
                }).join('') + '</div>';
            });
            if ((to.wo_type || '').toUpperCase() !== 'MAINTENANCE' && (parseInt(to.kabel_qty) > 0 || notes.kabel)) {
                html += `<div class="mt-2 text-[10px] text-slate-600"><b><i class="fa-solid fa-ruler-horizontal mr-1 text-slate-400"></i>Kabel:</b> ${tEsc(parseInt(to.kabel_qty) || 0)} Meter${notes.kabel ? ' - ' + tEsc(notes.kabel) : ''}</div>`;
            }
            return html ? `<div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">${html}</div>` : '';
        }

        async function loadTechHistory() {
            try {
                let res = await fetch('admin.php?api=get_technician_orders', { cache: 'no-store' });
                let json = await res.json();
                let container = document.getElementById('tech-history-container');
                
                if(json.success && json.data && json.data.length > 0) {
                    container.innerHTML = json.data.map(to => {
                        let isMaint = (to.wo_type || '').toUpperCase() === 'MAINTENANCE';
                        let typeBadge = isMaint ? 
                            `<span class="text-[10px] bg-rose-100 text-rose-800 font-extrabold px-2.5 py-1 rounded-full border border-rose-200"><i class="fa-solid fa-screwdriver-wrench mr-1"></i> MAINTENANCE</span>` : 
                            `<span class="text-[10px] bg-sky-100 text-sky-800 font-extrabold px-2.5 py-1 rounded-full border border-sky-200"><i class="fa-solid fa-house-chimney mr-1"></i> INSTALASI</span>`;

                        return `
                            <div class="bg-white rounded-3xl border border-slate-200/80 p-5 shadow-sm flex flex-col justify-between gap-4">
                                <div>
                                    <div class="flex justify-between items-center mb-3">
                                        <span class="text-xs font-black bg-slate-900 text-white px-2.5 py-1 rounded-xl">${to.id}</span>
                                        <div class="flex items-center gap-1.5 flex-wrap justify-end">${typeBadge}${renderTechStatusBadge(to)}</div>
                                    </div>
                                    <div class="mb-3">
                                        <h4 class="font-black text-slate-900 text-base mb-0.5">${to.nama}</h4>
                                        <span class="text-[11px] text-slate-400 font-medium">ID Pelanggan: <b>${to.customer_id}</b></span>
                                    </div>
                                    <div class="text-xs text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100 font-medium">
                                        <b>Deskripsi/Catatan:</b> ${to.catatan_maintenance || (to.kabel_qty ? to.kabel_qty + ' Meter Kabel' : '-')}
                                    </div>
                                    ${renderTechDevices(to)}
                                </div>
                                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
                                    <span class="text-[10px] text-slate-400 font-medium"><i class="fa-regular fa-clock mr-1"></i> ${to.created_at}</span>
                                    ${to.proof_image ? `<a href="${to.proof_image}" target="_blank" class="px-3 py-1.5 bg-sky-50 text-sky-600 rounded-xl text-xs font-bold flex items-center gap-1.5"><i class="fa-solid fa-image"></i> Lihat Foto</a>` : ''}
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                    container.innerHTML = '<div class="col-span-full text-center py-12 text-slate-400 font-bold bg-white rounded-3xl border border-slate-200">Belum ada riwayat pengerjaan work order.</div>';
                }
            } catch(e) {}
        }
    </script>
</body>
</html>