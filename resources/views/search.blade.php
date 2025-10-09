{{-- resources/views/search.blade.php --}}
<!doctype html>
<html>

<head>
  <meta charset="utf-8">
  <title>Hybrid Search</title>
  <style>
    body {
      font-family: sans-serif;
      max-width: 900px;
      margin: 24px auto
    }

    .item {
      padding: 12px;
      border-bottom: 1px solid #ddd
    }
  </style>
</head>

<body>
  <form method="get" action="{{ route('search') }}">
    <input type="text" name="q" value="{{ $q ?? '' }}" placeholder="اكتب سؤالك…" style="width:70%">
    <button>Search</button>
  </form>

  @if(!empty($q))
    <h3>Results for: “{{ $q }}”</h3>
    @forelse($results as $r)
      <div class="item">
        <div><strong>{{ $r->news_item_id }}</strong></div>
        <div><em>{{ $r->title }}</em></div>

        {{-- Snippet from best chunk (keeps ts_headline bolding) --}}
        <div>{!! $r->best_snippet !!}</div>

        {{-- Full article (collapsed by default) --}}
        <details style="margin-top:6px">
          <summary>Show full article</summary>
          @if(!empty($r->introduction))
            <p><strong>{{ $r->introduction }}</strong></p>
          @endif
          <p>{{ $r->body }}</p>
        </details>

        <small>doc_score={{ number_format($r->doc_score, 3) }}</small>
      </div>
    @empty
      <p>No results.</p>
    @endforelse

  @endif
</body>

</html>