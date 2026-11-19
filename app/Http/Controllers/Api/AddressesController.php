<?php

namespace App\Http\Controllers\Api;

use App\Models\Address;
use App\Models\Street;
use App\Models\StreetRoad;
use App\Models\Ward;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class AddressesController
{
    public function getRegions(): JsonResponse
    {
        return $this->locations('regions');
    }

    public function getDistricts(string $id): JsonResponse
    {
        return $this->locations('districts/'.rawurlencode($id));
    }

    public function getCouncils(string $id): JsonResponse
    {
        return $this->locations('skip_councils/'.rawurlencode($id));
    }

    public function getStreet(string $id): JsonResponse
    {
        return $this->locations('streets/'.rawurlencode($id));
    }

    public function getStreetRoad(string $id): JsonResponse
    {
        return $this->locations('drill_locations', ['location_id' => $id, 'skip' => 0]);
    }

    public function getAddress(string $id): JsonResponse
    {
        return $this->locations('skip_addresses/'.rawurlencode($id));
    }

    private function locations(string $endpoint, ?array $body = null): JsonResponse
    {
        $baseUrl = config('services.napa.url');
        abort_unless($baseUrl && config('services.napa.key'), 503, 'The delivery location service is unavailable.');
        $url = rtrim($baseUrl, '/').'/frontend_api/api/pub/'.$endpoint;

        try {
            $client = Http::withHeader('X-Napa-Api-Key', config('services.napa.key'))
                ->acceptJson()->connectTimeout(4)->timeout(12);
            $response = $body === null ? $client->get($url) : $client->post($url, $body);
        } catch (ConnectionException) {
            abort(502, 'Could not connect to the delivery location service.');
        }

        abort_unless($response->successful() && is_array($response->json('data')), 502, 'Could not load delivery locations.');

        return response()->json($response->json('data'));
    }

    public function createShipping(Request $request): JsonResponse
    {
        $data = $request->validate([
            'napa_ward_id' => ['required', 'exists:wards,napa_ward_id'],
            'napa_street_id' => ['required', 'string', 'max:100'],
            'street_name' => ['required', 'string', 'max:255'],
            'street_postcode' => ['nullable', 'string', 'max:50'],
            'napa_street_road_id' => ['required', 'string', 'max:100'],
            'street_road_name' => ['required', 'string', 'max:255'],
            'street_road_postcode' => ['nullable', 'string', 'max:50'],
            'contact_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:50'],
            'optional_phone_number' => ['nullable', 'string', 'max:50'],
            'number' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:100'],
            'trimedCode' => ['nullable', 'string', 'max:100'],
            'postcode' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'isDefault' => ['sometimes', 'boolean'],
        ]);

        $address = DB::transaction(function () use ($request, $data) {
            $ward = Ward::where('napa_ward_id', $data['napa_ward_id'])->firstOrFail();
            $street = Street::firstOrCreate(['napa_street_id' => $data['napa_street_id']], [
                'name' => $data['street_name'],
                'postcode' => $data['street_postcode'] ?? null,
                'region_id' => $ward->region_id,
                'district_id' => $ward->district_id,
                'ward_id' => $ward->id,
            ]);
            abort_unless((int) $street->ward_id === (int) $ward->id, 422, 'Street does not belong to the selected ward.');
            $road = StreetRoad::firstOrCreate(['napa_street_road_id' => $data['napa_street_road_id']], [
                'name' => $data['street_road_name'],
                'postcode' => $data['street_road_postcode'] ?? null,
                'street_id' => $street->id,
            ]);
            abort_unless((int) $road->street_id === (int) $street->id, 422, 'Road does not belong to the selected street.');

            return Address::create([
                ...$data,
                'customer_id' => $request->user()->id,
                'region_id' => $ward->region_id,
                'district_id' => $ward->district_id,
                'ward_id' => $ward->id,
                'street_id' => $street->id,
                'street_road_id' => $road->id,
            ]);
        });

        return response()->json(['message' => 'Address created successfully.', 'status' => 200, 'address' => $address]);
    }

    public function getCustomerAddresses(Request $request): JsonResponse
    {
        return response()->json(Address::where('customer_id', $request->user()->id)->orderByDesc('isDefault')->get());
    }
}
