<?php

declare(strict_types=1);

namespace Catalog\Infrastructure\Repository;

use Catalog\Application\ReadModel\SearchFilters;
use Catalog\Domain\Product;
use Catalog\Domain\Repository\ProductRepositoryInterface;
use Catalog\Domain\ValueObjects\Brand;
use Catalog\Domain\ValueObjects\Category;
use Catalog\Domain\ValueObjects\Price;
use Catalog\Domain\ValueObjects\ProductId;

final class InMemoryProductRepository implements ProductRepositoryInterface
{
    /** @var Category[] */
    private array $categories;
    /** @var Brand[] */
    private array $brands;
    /** @var Product[] */
    private array $products;

    public function __construct()
    {
        $this->categories = [
            new Category('cat-electronics', 'Electronics', 'electronics'),
            new Category('cat-books', 'Books', 'books'),
            new Category('cat-clothing', 'Clothing', 'clothing'),
            new Category('cat-home', 'Home & Kitchen', 'home'),
            new Category('cat-toys', 'Toys & Games', 'toys'),
        ];

        $this->brands = [
            new Brand('brand-acme', 'Acme'),
            new Brand('brand-globex', 'Globex'),
            new Brand('brand-initech', 'Initech'),
            new Brand('brand-umbrella', 'Umbrella'),
            new Brand('brand-stark', 'Stark'),
            new Brand('brand-wayne', 'Wayne'),
        ];

        $this->products = $this->seedProducts();
    }

    public function findByFilters(SearchFilters $filters, int $page, int $perPage): array
    {
        $matched = array_values(array_filter(
            $this->products,
            fn (Product $p): bool => $this->matches($p, $filters)
        ));

        usort($matched, static fn (Product $a, Product $b): int => strcmp($a->getName(), $b->getName()));

        $total = count($matched);
        $offset = ($page - 1) * $perPage;
        $slice = $offset >= $total ? [] : array_slice($matched, $offset, $perPage);

        return ['products' => $slice, 'total' => $total];
    }

    public function getFacets(): array
    {
        $prices = array_map(static fn (Product $p): int => $p->getPrice()->getAmount(), $this->products);

        return [
            'categories' => $this->categories,
            'brands' => $this->brands,
            'priceMin' => $prices === [] ? 0 : min($prices),
            'priceMax' => $prices === [] ? 0 : max($prices),
        ];
    }

    private function matches(Product $product, SearchFilters $filters): bool
    {
        $categoryIds = $filters->getCategoryIds();
        if ($categoryIds !== [] && !in_array($product->getCategoryId(), $categoryIds, true)) {
            return false;
        }
        $brandIds = $filters->getBrandIds();
        if ($brandIds !== [] && !in_array($product->getBrandId(), $brandIds, true)) {
            return false;
        }
        $amount = $product->getPrice()->getAmount();
        if ($filters->getPriceMin() !== null && $amount < $filters->getPriceMin()) {
            return false;
        }
        if ($filters->getPriceMax() !== null && $amount > $filters->getPriceMax()) {
            return false;
        }
        return $filters->getSearchTerm() === null || stripos($product->getName(), $filters->getSearchTerm()) !== false;
    }

    /**
     * @return Product[]
     */
    private function seedProducts(): array
    {
        $rows = [
            ['p01', 'Wireless Headphones', 'brand-stark', 'cat-electronics', 12999, 'Over-ear noise-cancelling cans.'],
            ['p02', 'Bluetooth Speaker', 'brand-acme', 'cat-electronics', 4999, 'Pocket-sized, all-day battery.'],
            ['p03', 'USB-C Charger 65W', 'brand-initech', 'cat-electronics', 2499, 'GaN tech, three ports.'],
            ['p04', 'Mechanical Keyboard', 'brand-wayne', 'cat-electronics', 8999, 'Hot-swappable, RGB optional.'],
            ['p05', '4K Webcam', 'brand-globex', 'cat-electronics', 6999, 'Auto-focus, dual mics.'],
            ['p06', 'Domain-Driven Design', 'brand-acme', 'cat-books', 3499, 'Eric Evans, the blue book.'],
            ['p07', 'Refactoring', 'brand-acme', 'cat-books', 2999, 'Fowler, 2nd edition.'],
            ['p08', 'The Pragmatic Programmer','brand-globex', 'cat-books', 2799, 'Hunt & Thomas.'],
            ['p09', 'Cotton T-Shirt', 'brand-umbrella', 'cat-clothing', 1499, 'Heavyweight, garment-dyed.'],
            ['p10', 'Denim Jeans', 'brand-umbrella', 'cat-clothing', 5999, 'Selvedge, slim fit.'],
            ['p11', 'Wool Sweater', 'brand-stark', 'cat-clothing', 7999, 'Merino, fisherman knit.'],
            ['p12', 'Running Shoes', 'brand-wayne', 'cat-clothing', 9999, 'Lightweight trainers.'],
            ['p13', 'Coffee Grinder', 'brand-initech', 'cat-home', 8499, 'Conical burr, 40 settings.'],
            ['p14', 'Cast Iron Skillet', 'brand-acme', 'cat-home', 3999, '10 inch, pre-seasoned.'],
            ['p15', 'Chef Knife', 'brand-stark', 'cat-home', 6499, '8 inch, German steel.'],
            ['p16', 'Espresso Machine', 'brand-globex', 'cat-home', 29999, 'Dual boiler, PID.'],
            ['p17', 'Board Game: Catan', 'brand-wayne', 'cat-toys', 3999, 'Trade, build, settle.'],
            ['p18', 'LEGO Architecture Set', 'brand-umbrella', 'cat-toys', 4999, '500+ pieces.'],
            ['p19', 'Drone Kit', 'brand-initech', 'cat-toys', 14999, '4K camera, foldable.'],
            ['p20', 'Puzzle 1000 pcs', 'brand-acme', 'cat-toys', 1999, 'Scenic landscape.'],
        ];

        $products = [];
        foreach ($rows as $r) {
            [$id, $name, $brandId, $categoryId, $cents, $summary] = $r;
            $products[] = new Product(
                new ProductId($id),
                $name,
                $brandId,
                $categoryId,
                new Price($cents, 'USD'),
                $this->placeholderImage($id, $name),
                $summary
            );
        }
        return $products;
    }

    private function placeholderImage(string $id, string $name): string
    {
        $hue = crc32($id) % 360;
        $bg = "hsl($hue, 60%, 75%)";
        $fg = "hsl($hue, 60%, 25%)";
        $initials = strtoupper(substr($name, 0, 2));
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">'
            . '<rect width="200" height="200" fill="' . $bg . '"/>'
            . '<text x="100" y="115" font-family="sans-serif" font-size="60" font-weight="700" '
            . 'text-anchor="middle" fill="' . $fg . '">' . htmlspecialchars($initials, ENT_XML1, 'UTF-8') . '</text>'
            . '</svg>';
        return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
    }
}
