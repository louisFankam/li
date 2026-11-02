<?php

namespace App\Http\Controllers;

use App\Exceptions\SyncRejectedException;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InsuranceCompany;
use App\Models\InsuranceRule;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\SyncChange;
use App\Models\SyncConflict;
use App\Models\SyncEvent;
use App\Models\User;
use App\Services\PharmacyContext;
use App\Services\SyncChangeRecorder;
use App\Services\SyncEventProcessor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use PDOException;
use Throwable;

class SyncController extends Controller
{
    public function push(Request $request, SyncEventProcessor $processor): JsonResponse
    {
        $request->validate([
            'device_token' => ['required', 'string'],
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.client_event_id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', 'max:60'],
            'events.*.payload' => ['present', 'array'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.pharmacy_id' => ['nullable', 'integer'],
            'events.*.schema_version' => ['nullable', 'integer', 'min:1'],
        ]);

        $device = $this->device($request);
        $device->update(['last_seen_at' => now()]);
        $user = $device->user;
        $accessible = $this->accessibleIds($user);

        $results = [];

        foreach ($request->input('events') as $event) {
            $clientEventId = $event['client_event_id'];
            $pharmacyId = $event['pharmacy_id'] ?? $device->pharmacy_id;
            $payload = $event['payload'];
            $payloadHash = $this->payloadHash($payload);

            if ($pharmacyId !== null && ! in_array((int) $pharmacyId, $accessible, true)) {
                $results[] = $this->result($clientEventId, 'rejected', 'Pharmacie inaccessible.');

                continue;
            }

            $existing = SyncEvent::query()->where('client_event_id', $clientEventId)->first();

            if ($existing !== null) {
                $existingHash = $existing->payload_hash ?? $this->payloadHash($existing->payload);

                if ((int) $existing->device_id !== $device->id
                    || (int) $existing->user_id !== $user->id
                    || (int) $existing->pharmacy_id !== ($pharmacyId !== null ? (int) $pharmacyId : null)
                    || ! hash_equals($existingHash, $payloadHash)) {
                    $results[] = $this->result($clientEventId, 'rejected', 'Identifiant d’événement déjà utilisé avec un autre contenu ou appareil.');

                    continue;
                }

                if ($existing->status === 'applied') {
                    $results[] = $this->attachSaleResource(
                        $this->result($clientEventId, 'duplicate'),
                        $existing,
                    );

                    continue;
                }

                if (in_array($existing->status, ['rejected', 'cancelled'], true)) {
                    $results[] = $this->result($clientEventId, 'rejected', $existing->error);

                    continue;
                }

                if ($existing->status === 'conflict') {
                    $results[] = $this->result($clientEventId, 'conflict', $existing->error);

                    continue;
                }

                $syncEvent = $existing;
            } else {
                $syncEvent = SyncEvent::create([
                    'client_event_id' => $clientEventId,
                    'pharmacy_id' => $pharmacyId,
                    'device_id' => $device->id,
                    'user_id' => $user->id,
                    'type' => $event['type'],
                    'payload' => $payload,
                    'payload_hash' => $payloadHash,
                    'schema_version' => $event['schema_version'] ?? 1,
                    'occurred_at' => $event['occurred_at'] ?? now(),
                    'received_at' => now(),
                    'status' => 'pending',
                ]);
            }

            $results[] = $this->attachSaleResource(
                $this->execute($syncEvent, $processor),
                $syncEvent,
            );
        }

        return response()->json(['results' => $results]);
    }

    public function pull(Request $request, SyncChangeRecorder $recorder): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'events' => ['nullable', 'string', 'max:4000'],
        ]);

        $device = $this->device($request);
        $device->update(['last_seen_at' => now()]);
        $accessible = $this->accessibleIds($device->user);
        $since = (int) $request->input('since', 0);
        $eventStatuses = $this->eventStatuses($request, $device);

        if ($since === 0) {
            // Lire le curseur avant le snapshot : toute écriture qui arrive
            // ensuite aura un identifiant supérieur et sera donc renvoyée au
            // pull suivant, même si le snapshot contenait un état plus récent.
            $cursor = (int) (SyncChange::query()->max('id') ?? 0);

            return response()->json([
                'server_time' => now()->toIso8601String(),
                'mode' => 'snapshot',
                'records' => $this->snapshot($recorder, $accessible),
                'next_cursor' => $cursor,
                'has_more' => false,
                'event_statuses' => $eventStatuses,
                'settings' => $this->settings(),
            ]);
        }

        $limit = (int) $request->input('limit', 100);
        $rows = SyncChange::query()
            ->where('id', '>', $since)
            ->where(function ($query) use ($accessible) {
                $query->whereNull('pharmacy_id')->orWhereIn('pharmacy_id', $accessible);
            })
            ->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'mode' => 'changes',
            'records' => $rows->map(fn (SyncChange $change) => $this->changeRecord($change))->values(),
            'next_cursor' => $rows->isNotEmpty() ? (int) $rows->last()->id : $since,
            'has_more' => $hasMore,
            'event_statuses' => $eventStatuses,
            'settings' => $this->settings(),
        ]);
    }

    private function execute(SyncEvent $event, SyncEventProcessor $processor): array
    {
        try {
            DB::transaction(function () use ($event, $processor) {
                $processor->apply($event);
                $event->update([
                    'status' => 'applied',
                    'error' => null,
                    'attempts' => $event->attempts + 1,
                    'processed_at' => now(),
                ]);
            });

            return $this->result($event->client_event_id, 'applied');
        } catch (SyncRejectedException $e) {
            $event->update([
                'status' => 'rejected',
                'error' => $e->getMessage(),
                'attempts' => $event->attempts + 1,
                'processed_at' => now(),
            ]);

            return $this->result($event->client_event_id, 'rejected', $e->getMessage());
        } catch (PDOException $e) {
            $event->update([
                'status' => 'failed',
                'error' => $e->getMessage(),
                'attempts' => $event->attempts + 1,
            ]);

            return $this->result($event->client_event_id, 'failed', 'Erreur temporaire, nouvelle tentative possible.');
        } catch (Throwable $e) {
            $event->update([
                'status' => 'conflict',
                'error' => $e->getMessage(),
                'attempts' => $event->attempts + 1,
            ]);

            SyncConflict::query()->firstOrCreate(
                ['sync_event_id' => $event->id, 'status' => 'open'],
                [
                    'pharmacy_id' => $event->pharmacy_id,
                    'type' => $event->type,
                    'details' => ['error' => $e->getMessage(), 'payload' => $event->payload],
                ],
            );

            return $this->result($event->client_event_id, 'conflict', $e->getMessage());
        }
    }

    /**
     * @param  array<int>  $accessible
     * @return array<int, array<string, mixed>>
     */
    private function snapshot(SyncChangeRecorder $recorder, array $accessible): array
    {
        $records = [];

        foreach (Product::query()->orderBy('id')->get() as $product) {
            $records[] = $this->snapshotRecord($recorder, $product, 'product', null);
        }
        foreach (Customer::query()->orderBy('id')->get() as $customer) {
            $records[] = $this->snapshotRecord($recorder, $customer, 'customer', null);
        }
        foreach (InsuranceCompany::query()->orderBy('id')->get() as $insurer) {
            $records[] = $this->snapshotRecord($recorder, $insurer, 'insurer', null);
        }
        foreach (InsuranceRule::query()->orderBy('id')->get() as $rule) {
            $records[] = $this->snapshotRecord($recorder, $rule, 'insurance_rule', null);
        }
        foreach (StockLevel::query()->whereIn('pharmacy_id', $accessible)->orderBy('id')->get() as $stock) {
            $records[] = $this->snapshotRecord($recorder, $stock, 'stock_level', $stock->pharmacy_id);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotRecord(SyncChangeRecorder $recorder, Model $model, string $type, ?int $pharmacyId): array
    {
        return [
            'entity_type' => $type,
            'operation' => 'upsert',
            'pharmacy_id' => $pharmacyId,
            'payload' => $recorder->payload($model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function changeRecord(SyncChange $change): array
    {
        return [
            'entity_type' => $change->entity_type,
            'operation' => $change->operation,
            'pharmacy_id' => $change->pharmacy_id,
            'payload' => $change->payload,
            'changed_at' => $change->changed_at->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function attachSaleResource(array $result, SyncEvent $event): array
    {
        if ($event->type !== 'sale.created' || ! in_array($result['status'], ['applied', 'duplicate'], true)) {
            return $result;
        }

        $clientRequestId = $event->payload['client_request_id'] ?? $event->client_event_id;
        $sale = Sale::query()
            ->where('client_request_id', $clientRequestId)
            ->where('pharmacy_id', $event->pharmacy_id)
            ->first();

        if ($sale !== null) {
            $result['sale'] = [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'total' => $sale->total,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function result(string $clientEventId, string $status, ?string $error = null): array
    {
        return array_filter([
            'client_event_id' => $clientEventId,
            'status' => $status,
            'error' => $error,
        ], fn ($value) => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        return [
            'discounts' => [
                'absolute_max_percent' => (int) config('discounts.absolute_max_percent', 50),
                'caps' => config('discounts.caps', []),
                'default' => config('discounts.default', []),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function eventStatuses(Request $request, Device $device): array
    {
        $ids = collect(explode(',', (string) $request->input('events', '')))
            ->map(fn (string $id) => trim($id))
            ->filter()
            ->unique()
            ->take(20)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return SyncEvent::query()
            ->where('device_id', $device->id)
            ->whereIn('client_event_id', $ids)
            ->pluck('status', 'client_event_id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function device(Request $request): Device
    {
        $token = (string) ($request->input('device_token') ?? $request->header('X-Device-Token', ''));
        abort_if($token === '', 401, 'Appareil non autorisé.');

        $device = Device::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('revoked_at')
            ->first();

        abort_unless($device !== null, 401, 'Appareil non autorisé.');

        return $device;
    }

    /**
     * @return array<int>
     */
    private function accessibleIds(User $user): array
    {
        return $user->is_super_admin
            ? Pharmacy::query()->pluck('id')->map(fn ($id) => (int) $id)->all()
            : $user->pharmacies()->pluck('pharmacies.id')->map(fn ($id) => (int) $id)->all();
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
        ]);

        $user = $request->user();
        $pharmacyId = $request->input('pharmacy_id');
        if ($pharmacyId !== null && ! $user->canAccessPharmacy(Pharmacy::find($pharmacyId))) {
            abort(403);
        }

        $rawToken = Str::random(64);
        $device = Device::create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacyId,
            'name' => $request->input('name', 'Navigateur'),
            'token_hash' => hash('sha256', $rawToken),
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'device_id' => $device->id,
            'device_token' => $rawToken,
        ]);
    }

    public function conflicts(Request $request): Response
    {
        $this->authorize('viewAny', SyncConflict::class);
        $accessible = app(PharmacyContext::class)->accessibleIds($request);

        /** @var Collection<int, SyncConflict> $rows */
        $rows = SyncConflict::with(['syncEvent', 'pharmacy'])
            ->whereIn('pharmacy_id', $accessible)
            ->where('status', 'open')
            ->latest()
            ->get();

        $conflicts = [];
        foreach ($rows as $c) {
            $conflicts[] = [
                'id' => $c->id,
                'sync_event_id' => $c->sync_event_id,
                'type' => $c->type,
                'pharmacy' => $c->pharmacy !== null ? (string) $c->pharmacy->getAttribute('name') : null,
                'details' => $c->details,
                'payload' => $c->syncEvent->payload,
                'created_at' => $c->created_at->format('d/m/Y H:i'),
            ];
        }

        return Inertia::render('SyncConflicts', ['conflicts' => $conflicts]);
    }

    public function resolveConflict(
        Request $request,
        SyncConflict $conflict,
        SyncEventProcessor $processor,
    ): RedirectResponse {
        $this->authorize('resolve', $conflict);
        $data = $request->validate([
            'resolution' => ['required', 'in:applied,cancelled,adjusted'],
            'payload' => ['required_if:resolution,adjusted', 'nullable', 'array'],
        ]);

        $event = $conflict->syncEvent;

        try {
            DB::transaction(function () use ($conflict, $event, $data, $request, $processor) {
                $conflict = SyncConflict::query()->whereKey($conflict->id)->lockForUpdate()->firstOrFail();
                $event = SyncEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

                if ($data['resolution'] === 'cancelled') {
                    $event->update([
                        'status' => 'rejected',
                        'error' => 'Annulé lors de la résolution du conflit.',
                        'processed_at' => now(),
                    ]);
                } else {
                    if ($data['resolution'] === 'adjusted') {
                        $event->update([
                            'payload' => $data['payload'],
                            'payload_hash' => $this->payloadHash($data['payload']),
                        ]);
                    }

                    $processor->apply($event);
                    $event->update([
                        'status' => 'applied',
                        'error' => null,
                        'processed_at' => now(),
                    ]);
                }

                $conflict->update([
                    'status' => $data['resolution'],
                    'resolved_by' => $request->user()->id,
                    'resolved_at' => now(),
                ]);
            });
        } catch (SyncRejectedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            return back()->with('error', 'Le conflit n’a pas pu être appliqué : '.$e->getMessage());
        }

        return redirect()->back()->with('success', 'Conflit résolu');
    }
}
