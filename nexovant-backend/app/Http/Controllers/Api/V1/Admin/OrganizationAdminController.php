<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Services\Organization\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class OrganizationAdminController
{
    public function __construct(
        protected OrganizationService $organizationService
    ) {}

    /**
     * List all organizations with administrative metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organization::with([
            'members.user:id,name,email',
            'subscriptions.plan',
        ]);

        if ($request->filled('search')) {
            $s = trim((string) $request->input('search'));
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('domain', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%")
                  ->orWhere('billing_email', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $status = $request->input('status');
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'suspended') {
                $query->where('is_active', false);
            }
        }

        $orgs = $query->latest('created_at')->paginate($request->integer('per_page', 15));

        $items = $orgs->getCollection()->map(function (Organization $org) {
            $owner = $org->members->firstWhere('role', 'owner')?->user;
            $activeSub = $org->activeSubscription();

            return [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'domain' => $org->domain,
                'domain_verified' => $org->isDomainVerified(),
                'billing_email' => $org->billing_email,
                'is_active' => $org->is_active,
                'max_seats' => $org->max_seats,
                'used_seats' => $org->members->count(),
                'available_seats' => max(0, $org->max_seats - $org->members->count()),
                'owner' => $owner ? [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'email' => $owner->email,
                ] : null,
                'plan' => $activeSub ? [
                    'name' => $activeSub->plan?->name,
                    'slug' => $activeSub->plan?->slug,
                    'status' => $activeSub->status,
                ] : [
                    'name' => 'Custom Enterprise',
                    'slug' => 'enterprise',
                    'status' => 'active',
                ],
                'created_at' => $org->created_at->format('c'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $orgs->currentPage(),
                'last_page' => $orgs->lastPage(),
                'per_page' => $orgs->perPage(),
                'total' => $orgs->total(),
            ],
        ]);
    }

    /**
     * Enterprise organization telemetry metrics.
     */
    public function metrics(): JsonResponse
    {
        $totalOrgs = Organization::count();
        $activeOrgs = Organization::where('is_active', true)->count();
        $totalMaxSeats = (int) Organization::sum('max_seats');
        $totalUsedSeats = OrganizationMember::count();
        $verifiedDomains = Organization::whereNotNull('domain_verified_at')->count();

        return response()->json([
            'success' => true,
            'metrics' => [
                'total_organizations' => $totalOrgs,
                'active_organizations' => $activeOrgs,
                'total_allocated_seats' => $totalMaxSeats,
                'active_members' => $totalUsedSeats,
                'seat_utilization_rate' => $totalMaxSeats > 0 ? round(($totalUsedSeats / $totalMaxSeats) * 100, 1) : 0,
                'verified_domains' => $verifiedDomains,
            ],
        ]);
    }

    /**
     * Show full organization details.
     */
    public function show(string $id): JsonResponse
    {
        $org = Organization::with([
            'members.user',
            'invitations',
            'subscriptions.plan',
            'auditLogs.user',
        ])->findOrFail($id);

        $assets = $this->organizationService->getOrganizationAssets($org);

        return response()->json([
            'success' => true,
            'organization' => $org,
            'assets' => $assets,
        ]);
    }

    /**
     * Admin creates an organization.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email'],
            'domain' => ['nullable', 'string', 'max:255'],
            'max_seats' => ['nullable', 'integer', 'min:1'],
            'billing_email' => ['nullable', 'email'],
        ]);

        $owner = User::where('email', $validated['owner_email'])->first();
        if (! $owner) {
            return response()->json([
                'success' => false,
                'message' => "User with email '{$validated['owner_email']}' not found. The user must register first before assigning as organization owner.",
            ], 422);
        }

        try {
            $org = $this->organizationService->createOrganization($owner, [
                'name' => $validated['name'],
                'domain' => $validated['domain'] ?? null,
                'max_seats' => $validated['max_seats'] ?? 10,
                'billing_email' => $validated['billing_email'] ?? $owner->email,
            ]);

            AuditLog::create([
                'id' => (string) Str::uuid(),
                'user_id' => $request->user()->id,
                'action' => 'admin.organization.created',
                'resource_type' => 'Organization',
                'resource_id' => $org->id,
                'metadata' => ['name' => $org->name, 'owner' => $owner->email],
                'created_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Organization created successfully.',
                'organization' => $org,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Admin updates organization details.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'domain' => ['nullable', 'string', 'max:255'],
            'billing_email' => ['sometimes', 'email'],
            'max_seats' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $org->update($validated);

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'action' => 'admin.organization.updated',
            'resource_type' => 'Organization',
            'resource_id' => $org->id,
            'metadata' => $validated,
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Organization updated successfully.',
            'organization' => $org,
        ]);
    }

    /**
     * Admin adjusts seat limit directly.
     */
    public function updateSeats(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);

        $validated = $request->validate([
            'max_seats' => ['required', 'integer', 'min:1'],
        ]);

        $oldSeats = $org->max_seats;
        $org->update(['max_seats' => $validated['max_seats']]);

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'action' => 'admin.organization.seats_adjusted',
            'resource_type' => 'Organization',
            'resource_id' => $org->id,
            'metadata' => [
                'old_seats' => $oldSeats,
                'new_seats' => $validated['max_seats'],
            ],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Seat quota adjusted from {$oldSeats} to {$validated['max_seats']}.",
            'organization' => $org,
        ]);
    }

    /**
     * Suspend or activate organization.
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $org->is_active = ! $org->is_active;
        $org->save();

        $action = $org->is_active ? 'activated' : 'suspended';

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'action' => "admin.organization.{$action}",
            'resource_type' => 'Organization',
            'resource_id' => $org->id,
            'metadata' => ['is_active' => $org->is_active],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Organization workspace has been {$action}.",
            'is_active' => $org->is_active,
        ]);
    }

    /**
     * Delete organization.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $name = $org->name;
        $org->delete();

        AuditLog::create([
            'id' => (string) Str::uuid(),
            'user_id' => $request->user()->id,
            'action' => 'admin.organization.deleted',
            'resource_type' => 'Organization',
            'resource_id' => $id,
            'metadata' => ['name' => $name],
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Organization '{$name}' deleted successfully.",
        ]);
    }
}
