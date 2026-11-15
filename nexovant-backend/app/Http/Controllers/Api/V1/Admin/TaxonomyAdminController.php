<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Category;
use App\Models\EditorialSeries;
use App\Models\Industry;
use App\Models\Product;
use App\Models\Subcategory;
use App\Models\Tag;
use App\Models\Technology;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TaxonomyAdminController
{
    /**
     * Get complete consolidated taxonomy tree.
     */
    public function index(): JsonResponse
    {
        $categories = Category::with(['subcategories', 'topics.children'])
            ->withCount('articles')
            ->orderBy('sort_order')
            ->get();

        $subcategories = Subcategory::with('category')->where('is_active', true)->orderBy('name')->get();
        $topics = Topic::with('parent')->where('is_active', true)->orderBy('name')->get();
        $industries = Industry::where('is_active', true)->orderBy('name')->get();
        $technologies = Technology::where('is_active', true)->orderBy('name')->get();
        $series = EditorialSeries::withCount('articles')->orderBy('title')->get();
        $tags = Tag::withCount('articles')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'topics' => $topics,
            'industries' => $industries,
            'technologies' => $technologies,
            'series' => $series,
            'editorial_series' => $series,
            'tags' => $tags,
        ]);
    }

    // =========================================================================
    // CATEGORIES
    // =========================================================================

    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:10'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'color' => $validated['color'] ?? '#00ADB5',
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Category '{$category->name}' created successfully.",
            'category' => $category,
        ], 201);
    }

    public function updateCategory(string $id, Request $request): JsonResponse
    {
        $category = Category::findOrFail($id);
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['sometimes', 'string', 'max:10'],
            'sort_order' => ['sometimes', 'integer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $category->update($validated);

        return response()->json([
            'success' => true,
            'message' => "Category '{$category->name}' updated.",
            'category' => $category,
        ]);
    }

    // =========================================================================
    // SUBCATEGORIES
    // =========================================================================

    public function storeSubcategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $sub = Subcategory::create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Subcategory '{$sub->name}' created.",
            'subcategory' => $sub,
        ], 201);
    }

    // =========================================================================
    // TOPICS & TOPIC HIERARCHIES
    // =========================================================================

    public function storeTopic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'parent_id' => ['nullable', 'exists:topics,id'],
            'description' => ['nullable', 'string'],
        ]);

        $topic = Topic::create([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'category_id' => $validated['category_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Topic '{$topic->name}' created in hierarchy.",
            'topic' => $topic->load(['parent', 'category']),
        ], 201);
    }

    // =========================================================================
    // TAGS & EDITORIAL SERIES
    // =========================================================================

    public function storeTag(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $tag = Tag::firstOrCreate(
            ['slug' => Str::slug($validated['name'])],
            ['name' => $validated['name']]
        );

        return response()->json([
            'success' => true,
            'message' => "Tag '#{$tag->name}' registered.",
            'tag' => $tag,
        ], 201);
    }

    public function categories(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Category::withCount('articles')->orderBy('sort_order')->get(),
        ]);
    }

    public function destroyCategory(string $id): JsonResponse
    {
        Category::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Category deleted.']);
    }

    public function subcategories(Request $request): JsonResponse
    {
        $query = Subcategory::with('category')->where('is_active', true);
        if ($catId = $request->query('category_id')) {
            $query->where('category_id', $catId);
        }
        return response()->json(['success' => true, 'data' => $query->orderBy('name')->get()]);
    }

    public function topics(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Topic::with('parent')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function tags(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Tag::withCount('articles')->orderBy('name')->get(),
        ]);
    }

    public function destroyTag(string $id): JsonResponse
    {
        Tag::findOrFail($id)->delete();
        return response()->json(['success' => true, 'message' => 'Tag deleted.']);
    }

    public function series(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => EditorialSeries::withCount('articles')->orderBy('title')->get(),
        ]);
    }
}
