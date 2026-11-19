<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Region;
use App\Models\Ward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NapaLocationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('services.napa.url') || ! config('services.napa.key')) {
            throw new RuntimeException('NAPA_BASE_URL and NAPA_API_KEY are required.');
        }

        foreach ($this->locations('regions') as $regionData) {
            $region = Region::updateOrCreate(
                ['napa_region_id' => $regionData['id']],
                ['name' => $regionData['name'], 'postcode' => $regionData['postcode'] ?? null],
            );
            foreach ($this->locations('districts/'.rawurlencode($region->napa_region_id)) as $districtData) {
                $district = District::updateOrCreate(
                    ['napa_district_id' => $districtData['id']],
                    ['name' => $districtData['name'], 'postcode' => $districtData['postcode'] ?? null, 'region_id' => $region->id],
                );
                foreach ($this->locations('skip_councils/'.rawurlencode($district->napa_district_id)) as $wardData) {
                    Ward::updateOrCreate(
                        ['napa_ward_id' => $wardData['id']],
                        ['name' => $wardData['name'], 'postcode' => $wardData['postcode'] ?? null,
                            'region_id' => $region->id, 'district_id' => $district->id],
                    );
                }
            }
        }
    }

    private function locations(string $endpoint): array
    {
        $url = rtrim(config('services.napa.url'), '/').'/frontend_api/api/pub/'.$endpoint;
        $results = Http::withHeader('X-Napa-Api-Key', config('services.napa.key'))
            ->acceptJson()->connectTimeout(4)->timeout(12)->get($url)->throw()->json('data');

        if (! is_array($results)) {
            throw new RuntimeException('NAPA returned an invalid location response.');
        }

        return $results;
    }
}
