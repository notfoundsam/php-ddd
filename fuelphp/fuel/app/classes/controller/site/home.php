<?php

use Audience\Site\Application\Query\SearchCatalogProducts\SearchCatalogProductsQuery;
use Audience\Site\Application\Query\ViewCatalogHomePage\ViewCatalogHomePageQuery;
use Fuel\Core\Input;

class Controller_Site_Home extends Controller_Site_Abstract
{
    public function action_index()
    {
        $query = ViewCatalogHomePageQuery::fromHttpInput(Input::get());
        $page = $this->queryBus->dispatch($query);

        return Blade::respond('site.home.index', [
            'title' => 'Shop',
            'products' => $page->getProducts(),
            'facets' => $page->getFacets(),
            'filters' => $query->getFilters(),
        ]);
    }

    public function action_products()
    {
        if (Input::headers('HX-Request') !== 'true') {
            $qs = Input::server('QUERY_STRING', '');
            return \Fuel\Core\Response::redirect('/' . ($qs !== '' ? '?' . $qs : ''));
        }

        $query = SearchCatalogProductsQuery::fromHttpInput(Input::get());
        $response = $this->queryBus->dispatch($query);

        $pushQuery = http_build_query(array_merge($query->getFilters()->toArray(), [
            'page' => $query->getPage(),
        ]));
        $pushUrl = '/' . ($pushQuery !== '' ? '?' . $pushQuery : '');

        return Blade::respond('site.home._grid', [
            'products' => $response->getResult(),
            'filters' => $query->getFilters(),
        ], 200, ['HX-Push-Url' => $pushUrl]);
    }
}
