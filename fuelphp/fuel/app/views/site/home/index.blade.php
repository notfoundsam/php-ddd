@extends('site.layout')

@section('content')
<div class="catalog-layout">
    <aside class="catalog-filters">
        @include('site.home._filters', ['facets' => $facets, 'filters' => $filters])
    </aside>
    <section class="catalog-content">
        <div id="product-grid">
            @include('site.home._grid', ['products' => $products, 'filters' => $filters])
        </div>
    </section>
</div>
@endsection
