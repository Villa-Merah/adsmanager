<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ads Manager</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<script>window.ADS_DATA={{ Illuminate\Support\Js::from(['campaigns'=>$campaigns,'leads'=>$leads,'metaConnection'=>$metaConnection,'user'=>auth()->user(),'users'=>$users,'canManage'=>auth()->user()->hasRole('admin','marketing'),'isAdmin'=>auth()->user()->hasRole('admin')]) }};</script>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 17V7a2 2 0 0 1 2-2h4v14H6a2 2 0 0 1-2-2Zm10 2V9h4a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-4ZM14 5h3a2 2 0 0 1 2 2h-5V5Z"/></svg>
            </div>
            <div><strong>Ads Manager</strong><span>Growth workspace</span></div>
        </div>

        <nav class="nav-menu" aria-label="Navigasi utama">
            <p class="nav-label">WORKSPACE</p>
            <button class="nav-item active" data-page="dashboard"><span class="nav-icon">⌂</span><span>Overview</span></button>
            <button class="nav-item" data-page="campaigns"><span class="nav-icon">◫</span><span>Campaign</span><span class="nav-count">2</span></button>
            <button class="nav-item" data-page="leads"><span class="nav-icon">♙</span><span>Leads</span><span class="nav-count">{{ $leads->count() }}</span></button>
            <button class="nav-item" data-page="content"><span class="nav-icon">▤</span><span>Konten</span></button>
            <p class="nav-label">ANALISIS</p>
            <button class="nav-item" data-page="funnel"><span class="nav-icon">▽</span><span>Funnel</span></button>
            <button class="nav-item" data-page="reports"><span class="nav-icon">↗</span><span>Laporan</span></button>
            @if(auth()->user()->hasRole('admin','marketing'))<button class="nav-item" data-page="imports"><span class="nav-icon">⇧</span><span>Impor Excel</span></button>@endif
        </nav>

        <div class="sidebar-bottom">
            <div class="usage-card">
                <div class="usage-icon">↟</div>
                <strong>Hubungkan sumber data</strong>
                <p>Sinkronkan Meta Ads untuk pembaruan otomatis.</p>
                @if(auth()->user()->hasRole('admin'))<button class="text-button" id="connectButton">{{ $metaConnection ? 'Sinkronkan sekarang →' : 'Hubungkan akun →' }}</button>@else<span class="role-note">Dikelola oleh Admin</span>@endif
            </div>
            <button class="nav-item" data-page="settings"><span class="nav-icon">⚙</span><span>Pengaturan</span></button>
            <div class="user-card">
                <button type="button" class="avatar profile-shortcut" data-go="settings" data-profile-avatar title="Pengaturan profil">@if(auth()->user()->avatar_url)<img src="{{ auth()->user()->avatar_url }}" alt="Foto profil">@else{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@endif</button>
                <div><strong data-profile-name>{{ auth()->user()->name }}</strong><span data-profile-email>{{ auth()->user()->email }}</span></div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="more" title="Keluar">↪</button></form>
            </div>
        </div>
    </aside>

    <div class="main-wrap">
        <header class="topbar">
            <button class="icon-button menu-toggle" id="menuToggle" aria-label="Buka menu">☰</button>
            <div class="breadcrumb"><span>Ads Manager</span><b>/</b><strong id="breadcrumbCurrent">Overview</strong></div>
            <div class="top-actions">
                <label class="search"><span>⌕</span><input id="globalSearch" type="search" placeholder="Cari campaign..."><kbd>⌘ K</kbd></label>
                <button class="icon-button notification" aria-label="Notifikasi">♢<i></i></button>
                @if(auth()->user()->hasRole('admin','marketing'))<button class="primary-button" id="newCampaignButton"><span>＋</span> Campaign baru</button>@endif
            </div>
        </header>

        <main class="content">
            <section class="page active" id="page-dashboard">
                <div class="page-heading">
                    <div><p class="eyebrow">RINGKASAN PERFORMA</p><h1>Selamat datang kembali 👋</h1><p>Pantau performa iklan dan temukan peluang pertumbuhan.</p></div>
                    <div class="heading-actions">
                        <button class="secondary-button" id="exportButton">⇩ Ekspor laporan</button>
                        <div class="date-range"><label>Dari<input type="date" id="dateFrom" value="{{ now()->startOfMonth()->toDateString() }}"></label><span>—</span><label>Sampai<input type="date" id="dateTo" value="{{ now()->toDateString() }}"></label><button class="primary-button" id="applyDate">Terapkan</button></div>
                    </div>
                </div>

                <div class="filter-strip">
                    <button class="filter-chip active" data-platform="Semua">Semua platform</button>
                    <button class="filter-chip" data-platform="Instagram"><span class="dot instagram"></span>Instagram</button>
                    <button class="filter-chip" data-platform="Facebook"><span class="dot facebook"></span>Facebook</button>
                    <button class="filter-chip" data-platform="Meta Ads">Meta Ads</button>
                    <span class="filter-divider"></span>
                    <label>Sumber konversi <select id="conversionSource"><option value="campaign">Laporan campaign</option><option value="crm">Catatan CRM</option></select></label>
                    <span class="updated"><i></i> <span id="updatedAt">Memuat data...</span></span>
                </div>

                <div class="metric-grid">
                    <article class="metric-card blue"><div class="metric-top"><span class="metric-icon">Rp</span></div><p>Total Budget</p><h2 id="metricBudget">0</h2><small>Budget pada periode terpilih</small></article>
                    <article class="metric-card purple"><div class="metric-top"><span class="metric-icon">◎</span></div><p>Impressions</p><h2 id="metricImpressions">0</h2><small>Akumulasi reach: <b id="metricReach">0</b></small></article>
                    <article class="metric-card cyan"><div class="metric-top"><span class="metric-icon">↗</span></div><p>Klik Link</p><h2 id="metricClicks">0</h2><small>CTR <b id="metricCtr">0</b></small></article>
                    <article class="metric-card orange"><div class="metric-top"><span class="metric-icon">♙</span></div><p>Leads</p><h2 id="metricLeads">0</h2><small>CPL <b id="metricCpl">0</b></small></article>
                    <article class="metric-card green"><div class="metric-top"><span class="metric-icon">✓</span></div><p>Closing</p><h2 id="metricClosing">0</h2><small>Rate <b id="metricClosingRate">0</b></small></article>
                    <article class="metric-card indigo"><div class="metric-top"><span class="metric-icon">◆</span></div><p>Omzet</p><h2 id="metricRevenue">0</h2><small>ROAS <b id="metricRoas">0</b></small></article>
                </div>

                <div class="analytics-grid">
                    <article class="panel performance-panel">
                        <div class="panel-head"><div><h3>Performa iklan</h3><p>Perbandingan budget dan omzet harian</p></div><div class="legend"><span><i class="legend-blue"></i>Budget</span><span><i class="legend-purple"></i>Omzet</span><button class="icon-button">•••</button></div></div>
                        <div class="chart-summary"><div><span>Total omzet</span><strong id="chartRevenue">Rp0</strong></div><div><span>ROAS</span><strong class="green-text" id="chartRoas">0x</strong></div></div>
                        <div id="performanceChart" style="padding:20px;max-height:360px;overflow:auto" aria-label="Grafik budget dan omzet harian"></div>
                    </article>

                    <article class="panel funnel-panel">
                        <div class="panel-head"><div><h3>Conversion funnel</h3><p>Alur dari impression hingga closing</p></div><button class="icon-button">•••</button></div>
                        <div class="funnel-list" id="overviewFunnel"></div>
                        <div class="funnel-insight"><p>Konversi mengikuti sumber angka yang dipilih. Reach dijumlahkan antar campaign dan tanggal; bukan jumlah akun unik seluruh periode.</p></div>
                    </article>
                </div>

                <article class="panel campaign-panel">
                    <div class="panel-head campaign-head"><div><h3>Performa campaign</h3><p>Periode berdasarkan tanggal campaign. Catatan CRM menghitung lead dan closing yang terkait campaign tersebut.</p></div><div><button class="link-button" data-go="campaigns">Lihat semua →</button></div></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>CAMPAIGN</th><th>PLATFORM</th><th>STATUS</th><th class="right">BUDGET</th><th class="right">LEADS</th><th class="right">CPL</th><th class="right">CLOSING</th><th class="right">OMZET</th><th class="right">ROAS</th><th></th></tr></thead>
                            <tbody id="campaignTableBody"></tbody>
                        </table>
                    </div>
                </article>
            </section>

            <section class="page" id="page-campaigns">
                <div class="page-heading"><div><p class="eyebrow">CAMPAIGN CENTER</p><h1>Semua campaign</h1><p>Kelola budget, status, dan performa campaign.</p></div>@if(auth()->user()->hasRole('admin','marketing'))<button class="primary-button" data-open-modal>＋ Campaign baru</button>@endif</div>
                <article class="panel"><div class="campaign-toolbar"><label class="search wide"><span>⌕</span><input id="campaignSearch" placeholder="Cari nama campaign"></label><select id="platformFilter"><option>Semua platform</option><option>Instagram</option><option>Facebook</option><option>Meta Ads</option></select><select id="campaignStatusFilter"><option>Semua status</option><option>Aktif</option><option>Selesai</option></select></div><div class="table-wrap"><table><thead><tr><th>CAMPAIGN</th><th>PLATFORM</th><th>STATUS</th><th class="right">BUDGET</th><th class="right">IMPRESSIONS</th><th class="right">LEADS</th><th class="right">CLOSING</th><th class="right">OMZET</th><th class="right">ROAS</th><th></th></tr></thead><tbody id="allCampaignTableBody"></tbody></table></div></article>
            </section>

            <section class="page" id="page-leads"><div class="page-heading"><div><p class="eyebrow">CRM PIPELINE</p><h1>Leads & follow-up</h1><p>Kelola prospek dari masuk hingga closing.</p></div>@if(auth()->user()->hasRole('admin','marketing'))<button class="primary-button" id="newLeadButton">＋ Lead baru</button>@endif</div><article class="panel"><div class="table-wrap"><table><thead><tr><th>NAMA</th><th>PROGRAM</th><th>CAMPAIGN</th><th>SUMBER</th><th>STATUS</th><th>FOLLOW UP</th><th></th></tr></thead><tbody id="leadTableBody"></tbody></table></div></article></section>
            <section class="page empty-page" id="page-content"><div class="empty-state"><span>▤</span><h2>Performa konten</h2><p>Analisis reach, klik, share, save, leads, dan closing per konten.</p><button class="primary-button">Tambah konten</button></div></section>
            <section class="page" id="page-funnel"><div class="page-heading"><div><p class="eyebrow">FULL FUNNEL</p><h1>Analisis funnel</h1><p>Temukan titik kehilangan terbesar sepanjang perjalanan audience.</p></div></div><article class="panel large-funnel"><div class="funnel-visual" id="fullFunnel"></div></article></section>
            <section class="page empty-page" id="page-reports"><div class="empty-state"><span>↗</span><h2>Laporan siap dibuat</h2><p>Ekspor ringkasan performa dalam format CSV untuk dibagikan.</p><button class="primary-button" id="reportExportButton">⇩ Ekspor sekarang</button></div></section>
            <section class="page" id="page-imports"><div class="page-heading"><div><p class="eyebrow">DATA INGESTION</p><h1>Impor workbook</h1><p>Gunakan sheet “Campaign Meta Ads” dari template Ads Manager.</p></div></div><article class="panel import-card"><div class="dropzone" id="dropzone"><span>⇧</span><h3>Unggah file Excel</h3><p>Format XLSX, maksimum 10 MB. File yang sama tidak akan diimpor dua kali.</p><input type="file" id="importFile" accept=".xlsx"><button class="secondary-button" id="chooseFileButton">Pilih file</button></div><div id="importPreview" class="import-preview"></div></article></section>
            <section class="page" id="page-settings"><div class="page-heading"><div><p class="eyebrow">AKUN & KEAMANAN</p><h1>Pengaturan</h1><p>Kelola identitas akun dan kata sandi.</p></div><span class="role-badge">{{ strtoupper(auth()->user()->role) }}</span></div><div class="settings-grid">@include('settings.profile')@if(auth()->user()->hasRole('admin'))<article class="panel settings-card meta-settings"><h3>Meta Ads</h3><p>{{ $metaConnection ? 'Terhubung ke '.$metaConnection->ad_account_id : 'Belum ada akun yang terhubung.' }}</p>@if($metaConnection)<div class="connection-status"><i></i><div><b>{{ $metaConnection->account_name ?: $metaConnection->ad_account_id }}</b><span>Sinkronisasi terakhir: {{ $metaConnection->last_synced_at?->diffForHumans() ?: 'belum pernah' }}</span></div></div><button class="danger-button" id="disconnectMeta">Putuskan koneksi</button>@else<button class="primary-button" id="settingsConnectMeta">Hubungkan Meta Ads</button>@endif</article><article class="panel settings-card team-settings"><div class="team-title"><div><h3>Anggota tim</h3><p>Admin, Marketing, dan Viewer.</p></div><button class="primary-button" id="newUserButton">＋ Anggota</button></div><div id="userList" class="user-list"></div></article>@endif</div></section>
        </main>
    </div>
</div>

<div class="modal-backdrop" id="campaignModal" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle">
        <div class="modal-head"><div><h2 id="modalTitle">Buat campaign baru</h2><p>Masukkan data dasar campaign iklan.</p></div><button class="icon-button" data-close-modal>×</button></div>
        <form id="campaignForm">
            <input type="hidden" name="id">
            <label>Nama campaign<input name="name" required placeholder="Contoh: Kelas Digital Marketing"></label>
            <div class="form-grid"><label>Tanggal<input name="date" type="date" value="2026-08-12" required></label><label>Objective<input name="objective" value="Leads" required></label></div>
            <div class="form-grid"><label>Platform<select name="platform"><option>Instagram</option><option>Facebook</option><option>Meta Ads</option><option>TikTok</option><option>Google</option></select></label><label>Status<select name="status"><option>Aktif</option><option>Draft</option><option>Selesai</option></select></label></div>
            <label>Budget (Rp)<input name="budget" type="number" min="0" required placeholder="5000000"></label>
            <div class="modal-actions"><button type="button" class="secondary-button" data-close-modal>Batal</button><button class="primary-button" id="campaignSubmit" type="submit">Simpan campaign</button></div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="leadModal" aria-hidden="true"><div class="modal"><div class="modal-head"><div><h2>Tambah lead</h2><p>Catat prospek baru dan sumbernya.</p></div><button class="icon-button" data-close-lead>×</button></div><form id="leadForm"><label>Nama<input name="name" required></label><div class="form-grid"><label>Kota<input name="city"></label><label>Program<input name="program"></label></div><div class="form-grid"><label>Sumber<select name="source"><option>Instagram</option><option>Facebook</option><option>WhatsApp</option><option>Referral</option></select></label><label>Status<select name="status"><option>Baru</option><option>Follow Up</option><option>Qualified</option></select></label></div><label>Campaign<select name="campaign_id" id="leadCampaignSelect"></select></label><div class="modal-actions"><button type="button" class="secondary-button" data-close-lead>Batal</button><button class="primary-button">Simpan lead</button></div></form></div></div>
<div class="modal-backdrop" id="metaModal" aria-hidden="true"><div class="modal"><div class="modal-head"><div><h2>Hubungkan Meta Ads</h2><p>Token disimpan terenkripsi dan tidak ditampilkan kembali.</p></div><button class="icon-button" data-close-meta>×</button></div><form id="metaForm"><label>Ad Account ID<input name="ad_account_id" placeholder="act_123456789" required></label><label>Nama akun<input name="account_name" placeholder="Opsional"></label><label>Access token<input name="access_token" type="password" autocomplete="off" required></label><div class="modal-actions"><button type="button" class="secondary-button" data-close-meta>Batal</button><button class="primary-button">Hubungkan</button></div></form></div></div>
<div class="modal-backdrop" id="userModal" aria-hidden="true"><div class="modal"><div class="modal-head"><div><h2>Tambah anggota</h2><p>Tetapkan akses sesuai tanggung jawab.</p></div><button class="icon-button" data-close-user>×</button></div><form id="userForm"><label>Nama<input name="name" required></label><label>Email<input name="email" type="email" required></label><div class="form-grid"><label>Peran<select name="role"><option value="marketing">Marketing</option><option value="viewer">Viewer</option><option value="admin">Admin</option></select></label><label>Kata sandi awal<input name="password" type="password" required></label></div><div class="modal-actions"><button type="button" class="secondary-button" data-close-user>Batal</button><button class="primary-button">Tambah anggota</button></div></form></div></div>

<div class="toast" id="toast"><span>✓</span><div><strong>Berhasil</strong><p id="toastMessage">Campaign telah ditambahkan.</p></div></div>
</body>
</html>
