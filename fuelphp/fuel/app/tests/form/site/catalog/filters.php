<?php

namespace Fuel\Core;

use Form_Site_Catalog_Filters;

/**
 * @group App
 * @group FormSiteCatalogFilters
 */
class Test_Form_Site_Catalog_Filters extends TestCase
{
    public function test_empty_array_produces_empty_filters(): void
    {
        $f = Form_Site_Catalog_Filters::parse([]);
        $this->assertTrue($f->isEmpty());
    }

    public function test_coerces_category_and_brand_arrays(): void
    {
        $f = Form_Site_Catalog_Filters::parse([
            'category' => ['cat-1', 'cat-2'],
            'brand' => ['brand-a'],
        ]);
        $this->assertSame(['cat-1', 'cat-2'], $f->getCategoryIds());
        $this->assertSame(['brand-a'], $f->getBrandIds());
    }

    public function test_drops_empty_strings_from_arrays(): void
    {
        $f = Form_Site_Catalog_Filters::parse([
            'category' => ['cat-1', '', 'cat-2'],
        ]);
        $this->assertSame(['cat-1', 'cat-2'], $f->getCategoryIds());
    }

    public function test_drops_non_string_values_from_arrays(): void
    {
        $f = Form_Site_Catalog_Filters::parse([
            'category' => ['cat-1', 42, null, ['nested'], 'cat-2'],
        ]);
        $this->assertSame(['cat-1', 'cat-2'], $f->getCategoryIds());
    }

    public function test_treats_non_array_category_as_empty(): void
    {
        $f = Form_Site_Catalog_Filters::parse(['category' => 'not-an-array']);
        $this->assertSame([], $f->getCategoryIds());
    }

    public function test_parses_numeric_string_price_bounds(): void
    {
        $f = Form_Site_Catalog_Filters::parse([
            'price_min' => '500',
            'price_max' => '5000',
        ]);
        $this->assertSame(500, $f->getPriceMin());
        $this->assertSame(5000, $f->getPriceMax());
    }

    public function test_treats_empty_price_string_as_null(): void
    {
        $f = Form_Site_Catalog_Filters::parse([
            'price_min' => '',
            'price_max' => '',
        ]);
        $this->assertNull($f->getPriceMin());
        $this->assertNull($f->getPriceMax());
    }

    public function test_rejects_non_numeric_price_strings(): void
    {
        $f = Form_Site_Catalog_Filters::parse([
            'price_min' => 'abc',
            'price_max' => '1.5',
        ]);
        $this->assertNull($f->getPriceMin());
        $this->assertNull($f->getPriceMax());
    }

    public function test_accepts_int_price_bounds(): void
    {
        $f = Form_Site_Catalog_Filters::parse([
            'price_min' => 100,
            'price_max' => 999,
        ]);
        $this->assertSame(100, $f->getPriceMin());
        $this->assertSame(999, $f->getPriceMax());
    }

    public function test_trims_search_term(): void
    {
        $f = Form_Site_Catalog_Filters::parse(['q' => '  hello  ']);
        $this->assertSame('hello', $f->getSearchTerm());
    }

    public function test_treats_blank_search_term_as_null(): void
    {
        $f = Form_Site_Catalog_Filters::parse(['q' => '   ']);
        $this->assertNull($f->getSearchTerm());
    }

    public function test_ignores_non_string_search_term(): void
    {
        $f = Form_Site_Catalog_Filters::parse(['q' => ['not-a-string']]);
        $this->assertNull($f->getSearchTerm());
    }

    public function test_page_defaults_to_one_when_absent(): void
    {
        $this->assertSame(1, Form_Site_Catalog_Filters::parsePage([]));
    }

    public function test_page_parses_numeric_string(): void
    {
        $this->assertSame(3, Form_Site_Catalog_Filters::parsePage(['page' => '3']));
    }

    public function test_page_accepts_int(): void
    {
        $this->assertSame(5, Form_Site_Catalog_Filters::parsePage(['page' => 5]));
    }

    public function test_page_coerces_non_numeric_to_one(): void
    {
        $this->assertSame(1, Form_Site_Catalog_Filters::parsePage(['page' => 'abc']));
    }

    public function test_page_clamps_negative_int_to_one(): void
    {
        $this->assertSame(1, Form_Site_Catalog_Filters::parsePage(['page' => -2]));
    }

    public function test_page_clamps_zero_int_to_one(): void
    {
        $this->assertSame(1, Form_Site_Catalog_Filters::parsePage(['page' => 0]));
    }
}
