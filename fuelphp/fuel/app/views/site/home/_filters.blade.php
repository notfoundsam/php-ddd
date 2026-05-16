@php
    /** @var \Catalog\Application\ReadModel\FilterFacets $facets */
    /** @var \Catalog\Application\ReadModel\SearchFilters $filters */
    $selectedCategories = $filters->getCategoryIds();
    $selectedBrands = $filters->getBrandIds();
    $priceMin = $filters->getPriceMin();
    $priceMax = $filters->getPriceMax();
    $q = $filters->getSearchTerm();
@endphp
<form class="filters"
      action="/"
      method="get"
      hx-get="/products"
      hx-target="#product-grid"
      hx-swap="innerHTML"
      hx-trigger="change, input changed delay:400ms from:.price-input">

    @if ($q !== null)
        <input type="hidden" name="q" value="{{ $q }}">
    @endif

    <fieldset class="filter-group">
        <legend>Category</legend>
        @foreach ($facets->getCategories() as $category)
            <label class="filter-option">
                <input type="checkbox"
                       name="category[]"
                       value="{{ $category->getId() }}"
                       {{ in_array($category->getId(), $selectedCategories, true) ? 'checked' : '' }}>
                {{ $category->getName() }}
            </label>
        @endforeach
    </fieldset>

    <fieldset class="filter-group">
        <legend>Brand</legend>
        @foreach ($facets->getBrands() as $brand)
            <label class="filter-option">
                <input type="checkbox"
                       name="brand[]"
                       value="{{ $brand->getId() }}"
                       {{ in_array($brand->getId(), $selectedBrands, true) ? 'checked' : '' }}>
                {{ $brand->getName() }}
            </label>
        @endforeach
    </fieldset>

    <fieldset class="filter-group">
        <legend>Price (cents)</legend>
        <input class="price-input"
               type="number"
               name="price_min"
               min="{{ $facets->getPriceMin() }}"
               max="{{ $facets->getPriceMax() }}"
               placeholder="from"
               value="{{ $priceMin !== null ? (int) $priceMin : '' }}">
        <input class="price-input"
               type="number"
               name="price_max"
               min="{{ $facets->getPriceMin() }}"
               max="{{ $facets->getPriceMax() }}"
               placeholder="to"
               value="{{ $priceMax !== null ? (int) $priceMax : '' }}">
    </fieldset>

    <noscript>
        <button type="submit" class="btn btn-primary">Apply filters</button>
    </noscript>
</form>
