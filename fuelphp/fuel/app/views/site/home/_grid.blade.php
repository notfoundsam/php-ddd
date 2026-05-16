@php
    /** @var \Catalog\Application\ReadModel\ProductSearchResult $products */
    /** @var \Catalog\Application\ReadModel\SearchFilters $filters */
    $total = $products->getTotal();
    $currentPage = $products->getPage();
    $totalPages = $products->getTotalPages();
    $filterQuery = $filters->toArray();
@endphp
<div class="grid-header">
    <p>Found <strong>{{ $total }}</strong> item{{ $total === 1 ? '' : 's' }}</p>
</div>

@if (count($products->getItems()) === 0)
    <p class="grid-empty">No products match the selected filters.</p>
@else
    <ul class="product-grid">
        @foreach ($products->getItems() as $item)
            @include('site.home._card', ['p' => $item])
        @endforeach
    </ul>
@endif

@if ($totalPages > 1)
    <nav class="pagination" aria-label="Pagination">
        @for ($i = 1; $i <= $totalPages; $i++)
            @php $query = array_merge($filterQuery, ['page' => $i]); @endphp
            <a href="/?{{ http_build_query($query) }}"
               hx-get="/products?{{ http_build_query($query) }}"
               hx-target="#product-grid"
               hx-swap="innerHTML"
               class="pagination-link {{ $i === $currentPage ? 'is-active' : '' }}">{{ $i }}</a>
        @endfor
    </nav>
@endif
