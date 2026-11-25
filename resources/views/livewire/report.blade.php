@php
    $fmt = fn ($n) => 'Rp ' . number_format(abs($n), 0, ',', '.');
    // Perubahan dibanding bulan lalu; null bila bulan lalu kosong (tidak bisa dipersenkan)
    $delta = function ($now, $before) {
        if ($before <= 0) return null;
        return round(($now - $before) / $before * 100);
    };
    $dExp = $delta($expense, $prevExpense);
    $dInc = $delta($income, $prevIncome);
@endphp

<div class="stack-lg">
    <div class="row-between">
        <h1 class="page-title">Laporan</h1>
        <a class="btn btn-sm btn-ghost" href="{{ route('export', ['bulan' => $month]) }}" download>⬇ CSV bulan ini</a>
    </div>

    <div class="month-nav">
        <button type="button" class="icon-btn" wire:click="prev" aria-label="Bulan sebelumnya">‹</button>
        <strong>{{ $start->translatedFormat('F Y') }}</strong>
        <button type="button" class="icon-btn" wire:click="next" @disabled($isCurrent) aria-label="Bulan berikutnya">›</button>
    </div>

    <section class="balance-card">
        <span class="label">Sisa bulan ini</span>
        <strong class="balance {{ $balance < 0 ? 'neg' : '' }}">{{ $balance < 0 ? '−' : '' }}{{ $fmt($balance) }}</strong>
        <div class="balance-split">
            <div>
                <span class="label">▲ Pemasukan</span>
                <b>{{ $fmt($income) }}</b>
                @if ($dInc !== null) <small class="delta">{{ $dInc >= 0 ? '+' : '−' }}{{ abs($dInc) }}% vs bulan lalu</small> @endif
            </div>
            <div>
                <span class="label">▼ Pengeluaran</span>
                <b>{{ $fmt($expense) }}</b>
                @if ($dExp !== null) <small class="delta">{{ $dExp >= 0 ? '+' : '−' }}{{ abs($dExp) }}% vs bulan lalu</small> @endif
            </div>
        </div>
    </section>

    @if ($count === 0)
        <p class="card empty">Belum ada transaksi di bulan ini.</p>
    @else
        @if ($income > 0)
            <section class="card">
                <h2 class="section-title">Tingkat tabungan</h2>
                @php $rate = round(($income - $expense) / $income * 100); @endphp
                <p class="rate {{ $rate < 0 ? 'neg' : '' }}">{{ $rate }}%</p>
                <p class="muted">{{ $rate >= 0 ? 'Dari tiap Rp 100 pemasukan, ' . max(0, $rate) . ' rupiah tersisa.' : 'Pengeluaran melebihi pemasukan bulan ini.' }}</p>
            </section>
        @endif

        <section class="card">
            <h2 class="section-title">Pengeluaran per kategori</h2>
            @forelse ($expenseRows as $r)
                <div class="bar-row">
                    <div class="bar-head">
                        <span>{{ $r->icon }} {{ $r->name }} <small class="muted">· {{ $r->n }}×</small></span>
                        <b>{{ $fmt($r->total) }} <small class="muted">{{ round($r->pct) }}%</small></b>
                    </div>
                    <div class="bar-track"><div class="bar-fill" style="width: {{ max(3, round($r->pct)) }}%; background: {{ $r->color }}"></div></div>
                </div>
            @empty
                <p class="empty">Tidak ada pengeluaran.</p>
            @endforelse
        </section>

        @if ($incomeRows->isNotEmpty())
            <section class="card">
                <h2 class="section-title">Pemasukan per kategori</h2>
                @foreach ($incomeRows as $r)
                    <div class="bar-row">
                        <div class="bar-head">
                            <span>{{ $r->icon }} {{ $r->name }} <small class="muted">· {{ $r->n }}×</small></span>
                            <b>{{ $fmt($r->total) }} <small class="muted">{{ round($r->pct) }}%</small></b>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ max(3, round($r->pct)) }}%; background: {{ $r->color }}"></div></div>
                    </div>
                @endforeach
            </section>
        @endif

        @if ($byUser->isNotEmpty())
            <section class="card">
                <h2 class="section-title">Pengeluaran per orang</h2>
                @foreach ($byUser as $u)
                    <div class="bar-row">
                        <div class="bar-head">
                            <span>{{ $u->name }}{{ $u->name === auth()->user()->name ? ' (kamu)' : '' }}</span>
                            <b>{{ $fmt($u->total) }}</b>
                        </div>
                        <div class="bar-track"><div class="bar-fill" style="width: {{ $expense > 0 ? max(3, round($u->total / $expense * 100)) : 0 }}%; background: var(--brand)"></div></div>
                    </div>
                @endforeach
            </section>
        @endif
    @endif

    <a class="btn btn-ghost btn-block" href="{{ route('export') }}" download>⬇ Ekspor semua transaksi (CSV)</a>
</div>
