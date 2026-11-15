<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Subscriber;

use App\Services\Workspace\PersonalLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersonalLibraryController
{
    public function __construct(
        protected PersonalLibraryService $libraryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->libraryService->listItems($request->user(), $request->all(), 20);

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', 'string', 'in:article,publication,research_report,intelligence_event'],
            'item_id' => ['required', 'string'],
            'collection_id' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $item = $this->libraryService->saveItem($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Intelligence record saved to your private library.',
            'item' => $item,
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'collection_id' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'private_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $item = $this->libraryService->updateItem($request->user(), $id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Library record updated.',
            'item' => $item,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->libraryService->removeItem($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Record removed from private library.',
        ]);
    }

    public function collections(Request $request): JsonResponse
    {
        $collections = $this->libraryService->listCollections($request->user());

        return response()->json([
            'success' => true,
            'collections' => $collections,
        ]);
    }

    public function storeCollection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:400'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $collection = $this->libraryService->createCollection($request->user(), $validated);

        return response()->json([
            'success' => true,
            'message' => 'Collection created.',
            'collection' => $collection,
        ], 201);
    }

    public function updateCollection(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:400'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $collection = $this->libraryService->updateCollection($request->user(), $id, $validated);

        return response()->json([
            'success' => true,
            'message' => 'Collection updated.',
            'collection' => $collection,
        ]);
    }

    public function destroyCollection(Request $request, string $id): JsonResponse
    {
        $this->libraryService->deleteCollection($request->user(), $id);

        return response()->json([
            'success' => true,
            'message' => 'Collection deleted.',
        ]);
    }

    public function export(Request $request, string $id): JsonResponse
    {
        $format = $request->query('format', 'json');
        $exported = $this->libraryService->exportCollection($request->user(), $id, $format);

        return response()->json([
            'success' => true,
            'format' => $format,
            'export' => $exported,
        ]);
    }
}
