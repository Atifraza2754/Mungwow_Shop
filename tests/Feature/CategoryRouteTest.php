<?php

namespace Tests\Feature;

use App\Models\Category;
use Tests\TestCase;

class CategoryRouteTest extends TestCase
{
    public function test_home_page_loads_successfully(): void
    {
        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('category-btn active" data-cat="all"', false);
    }

    public function test_direct_category_slug_loads_successfully(): void
    {
        $category = Category::where('is_active', true)->first();
        if ($category) {
            $slug = \Illuminate\Support\Str::slug($category->name);
            $response = $this->get('/' . $slug);
            $response->assertStatus(200);
            $response->assertSee('data-cat="' . $category->id . '"', false);
        }
    }

    public function test_direct_grocery_category_loads_successfully(): void
    {
        $response = $this->get('/grocery');
        $response->assertStatus(200);
        $response->assertSee('Custom Grocery List');
    }

    public function test_standard_routes_are_not_shadowed(): void
    {
        $response = $this->get('/cart');
        $response->assertStatus(200);

        $response = $this->get('/track');
        $response->assertStatus(200);
    }
}
