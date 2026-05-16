@php /** @var \Catalog\Application\ReadModel\ProductListItem $p */ @endphp
<li class="product-card">
    <a href="/products/{{ $p->getId() }}" class="product-card-link">
        <img class="product-card-image"
             src="{{ $p->getImageUrl() }}"
             alt=""
             loading="lazy"
             width="200"
             height="200">
        <h3 class="product-card-name">{{ $p->getName() }}</h3>
        <p class="product-card-brand">{{ $p->getBrandName() }}</p>
        <p class="product-card-price">{{ $p->getPriceFormatted() }}</p>
    </a>
</li>
