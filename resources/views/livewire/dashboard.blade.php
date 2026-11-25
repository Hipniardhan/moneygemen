<div class="stack-lg">
    <div class="row-between">
        <h1 class="page-title">Halo, {{ auth()->user()->name }}</h1>
        <div class="seg seg-sm" role="tablist" aria-label="Periode">
            <button type="button" wire:click="setPeriod('month')" class="{{ $period === 'month' ? 'on' : '' }}">Bulan ini</button>
            <button type="button" wire:click="setPeriod('all')" class="{{ $period === 'all' ? 'on' : '' }}">Semua</button>
        </div>
    </div>

    <section class="balance-card">
        <span class="label">Saldo</span>
        <div class="balance-row">
            <strong class="balance {{ $balance < 0 ? 'neg' : '' }}">
                <span class="bal-real">{{ $balance < 0 ? '−' : '' }}Rp {{ number_format(abs($balance), 0, ',', '.') }}</span>
                <span class="bal-mask" aria-hidden="true">Rp ••••••</span>
            </strong>
            <button type="button" class="eye" onclick="toggleBalance()" aria-label="Sembunyikan atau tampilkan saldo" title="Sembunyikan / tampilkan saldo">
                <svg class="eye-on" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg class="eye-off" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.9 17.9A10.5 10.5 0 0 1 12 19c-6.4 0-10-7-10-7a18 18 0 0 1 5.1-5.9M9.9 5.2A9.7 9.7 0 0 1 12 5c6.4 0 10 7 10 7a18 18 0 0 1-2.2 3.2"/><path d="M14.1 14.1a3 3 0 1 1-4.2-4.2"/><path d="m2 2 20 20"/></svg>
            </button>
        </div>
        <div class="balance-split">
            <div>
                <span class="label">▲ Pemasukan</span>
                <b>Rp {{ number_format($income, 0, ',', '.') }}</b>
            </div>
            <div>
                <span class="label">▼ Pengeluaran</span>
                <b>Rp {{ number_format($expense, 0, ',', '.') }}</b>
            </div>
        </div>
    </section>

    <section class="card" x-data="{ sel: null }">
        <h2 class="section-title">Akumulasi per kategori</h2>

        @if ($chart['income']->isEmpty() && $chart['expense']->isEmpty())
            <p class="empty">Belum ada transaksi{{ $period === 'month' ? ' bulan ini' : '' }}.</p>
        @else
            <div class="chart">
                @foreach (['income' => '▲ Pemasukan', 'expense' => '▼ Pengeluaran'] as $type => $label)
                    <div class="chart-group {{ $type }}">
                        <div class="chart-cols">
                            @forelse ($chart[$type] as $row)
                                @php $key = $type . '-' . $row->id; @endphp
                                <button type="button" class="col" :class="{ on: sel && sel.k === '{{ $key }}' }"
                                        aria-label="{{ $row->name }}: Rp {{ number_format($row->total, 0, ',', '.') }}"
                                        @click="sel = (sel && sel.k === '{{ $key }}') ? null : @js(['k' => $key, 'icon' => $row->icon, 'name' => $row->name, 'amount' => 'Rp ' . number_format($row->total, 0, ',', '.'), 'type' => $label])">
                                    <span class="col-val">{{ \App\Services\Rupiah::compact($row->total) }}</span>
                                    <span class="col-track"><span class="col-bar" style="height: {{ max(3, round($row->total / $chartMax * 100)) }}%; background: {{ $row->color }}"></span></span>
                                    <span class="col-ico" aria-hidden="true">{{ $row->icon }}</span>
                                </button>
                            @empty
                                <span class="chart-none">Belum ada</span>
                            @endforelse
                        </div>
                        <div class="chart-label">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
            <p class="chart-detail" aria-live="polite">
                <template x-if="sel"><span><b x-text="sel.icon + ' ' + sel.name"></b> · <span x-text="sel.type"></span> <b x-text="sel.amount"></b></span></template>
                <span x-show="!sel" class="muted">Ketuk kolom untuk melihat detail.</span>
            </p>
        @endif
    </section>

    <a href="{{ route('report') }}" class="report-link">
        <span>📊 Laporan bulanan &amp; ekspor CSV</span>
        <span aria-hidden="true">›</span>
    </a>

    <section class="card">
        <h2 class="section-title">5 pengeluaran terbesar</h2>
        @forelse ($top as $row)
            <div class="bar-row">
                <div class="bar-head">
                    <span>{{ $row->icon }} {{ $row->name }}</span>
                    <b>Rp {{ number_format($row->total, 0, ',', '.') }}</b>
                </div>
                <div class="bar-track"><div class="bar-fill" style="width: {{ max(3, round($row->total / $topMax * 100)) }}%; background: {{ $row->color }}"></div></div>
            </div>
        @empty
            <p class="empty">Belum ada pengeluaran{{ $period === 'month' ? ' bulan ini' : '' }}.</p>
        @endforelse
    </section>

    <section>
        <div class="row-between">
            <h2 class="section-title">Transaksi terbaru</h2>
            <a href="{{ route('transactions') }}" class="link-sm">Lihat semua</a>
        </div>
        <div class="seg" role="tablist" aria-label="Filter transaksi">
            <button type="button" wire:click="setTab('all')" class="{{ $tab === 'all' ? 'on' : '' }}">Semua</button>
            <button type="button" wire:click="setTab('income')" class="{{ $tab === 'income' ? 'on' : '' }}">Pemasukan</button>
            <button type="button" wire:click="setTab('expense')" class="{{ $tab === 'expense' ? 'on' : '' }}">Pengeluaran</button>
        </div>

        <div class="list card-flush">
            @forelse ($recent as $t)
                @include('partials.transaction-row', ['t' => $t, 'hasPartner' => $hasPartner])
            @empty
                <p class="empty">Belum ada transaksi. Tekan tombol + untuk mencatat.</p>
            @endforelse
        </div>
    </section>
</div>
