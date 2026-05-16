<header class="site-header">
    <a class="site-logo" href="/">Shop</a>
    <form class="site-search" action="/" method="get" role="search">
        <input type="search" name="q" placeholder="Search products…" value="{{ isset($filters) ? ($filters->getSearchTerm() ?? '') : '' }}">
        <button type="submit">Search</button>
    </form>
    <nav class="site-nav">
        <a class="btn btn-secondary" href="/login">Login</a>
        <a class="btn btn-primary" href="/register">Register</a>
    </nav>
</header>
