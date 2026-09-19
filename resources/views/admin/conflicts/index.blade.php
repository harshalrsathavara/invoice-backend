@extends('admin.layout')
@section('title', 'Sync conflicts')

@section('content')
    <div class="page-head">
        <div>
            <div class="eyebrow">Mobile app</div>
            <h1>Sync conflicts</h1>
            <div class="sub">Changes that lost a last-write-wins comparison. Nothing here was applied — it is kept so a discarded edit is visible rather than silently gone.</div>
        </div>
        <a href="{{ route('admin.conflicts.index', ['all' => $showingAll ? null : 1]) }}" class="btn ghost small">
            {{ $showingAll ? 'Show unreviewed only' : 'Show all' }}
        </a>
    </div>

    @forelse($conflicts as $conflict)
        <section class="card" style="margin-bottom:16px">
            <h2>
                {{ $conflict->model_type }} · {{ $conflict->created_at->format('d M Y, H:i') }}
                @if(! $conflict->reviewed)
                    <form method="POST" action="{{ route('admin.conflicts.review', $conflict) }}">
                        @csrf
                        <button type="submit" class="btn ghost small">Mark reviewed</button>
                    </form>
                @else
                    <span class="pill paid">Reviewed</span>
                @endif
            </h2>
            <div class="body">
                <dl class="kv" style="margin-bottom:14px">
                    <dt>Row</dt><dd class="mono" style="font-size:12px;word-break:break-all">{{ $conflict->uuid }}</dd>
                    <dt>From</dt><dd>{{ $conflict->syncLog?->device?->name ?? 'Unknown device' }}</dd>
                    <dt>Outcome</dt><dd>The server's copy was kept.</dd>
                </dl>

                <div class="grid two">
                    <div>
                        <div class="label muted" style="font-size:10.5px;letter-spacing:.09em;text-transform:uppercase;margin-bottom:6px">Discarded — from the handset</div>
                        <pre class="json">{{ json_encode($conflict->incoming, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                    <div>
                        <div class="label muted" style="font-size:10.5px;letter-spacing:.09em;text-transform:uppercase;margin-bottom:6px">Kept — on the server</div>
                        <pre class="json">{{ json_encode($conflict->existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    </div>
                </div>
            </div>
        </section>
    @empty
        <section class="card">
            <div class="empty">
                <strong>Nothing to review</strong>
                Every change that arrived has been applied cleanly.
            </div>
        </section>
    @endforelse

    {{ $conflicts->links() }}
@endsection
