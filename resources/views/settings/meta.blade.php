<article class="panel settings-card meta-settings">
<h3>Meta Ads per cabang</h3><p>Kelola akun iklan cabang Anda. Sinkronisasi memakai periode yang dipilih di Overview.</p>
@foreach([$branch] as $name)
@php($connection=$metaConnections->firstWhere('branch',$name))
<div class="branch-meta-card"><h4>{{ $name }}</h4>
@if($connection)
<div class="connection-status"><i></i><div><b>{{ $connection->account_name ?: $connection->ad_account_id }}</b><span>{{ $connection->ad_account_id }} · {{ $connection->status }}</span><span>Sinkronisasi terakhir: {{ $connection->last_synced_at?->diffForHumans() ?: 'belum pernah' }}</span></div></div>
<button class="secondary-button" data-sync-meta="{{ $name }}">Sinkronkan</button> <button class="danger-button" data-disconnect-meta="{{ $name }}">Putuskan koneksi</button>
@else<p>Belum terhubung.</p><button class="secondary-button" data-connect-meta="{{ $name }}">Hubungkan akun</button>@endif
</div>
@endforeach
</article>
