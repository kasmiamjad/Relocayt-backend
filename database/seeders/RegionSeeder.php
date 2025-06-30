<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RegionSeeder extends Seeder
{
    private array $regionAllow = ['*'];
    private array $countryAllow = ['*'];
    private array $cityAllow = ['*'];
    private array $areaAllow = ['*'];

    public function run(): void
    {
        $default = data_get(Language::where('default', 1)->first(), 'locale', 'en');

        $filePath = database_path('seeders/json/countries.json');
        $data = collect(json_decode(file_get_contents($filePath)))->lazy();


        foreach ($data as $item) {
            $title = data_get($item, 'region');
            if ($title == 'Americas') {
                $title = 'America';
            }

            if (!in_array($title, $this->regionAllow) && !in_array('*', $this->regionAllow)) {
                continue;
            }

            $region = $this->region($item, $default, $title);

            if (!in_array(data_get($item, 'name'), $this->countryAllow) && !in_array('*', $this->countryAllow)) {
                continue;
            }

            $country = $this->country($region, $item, $default);

            if (!isset($item->states)) {
                continue;
            }

            foreach ($item->states as $state) {
                if (!in_array(data_get($state, 'name'), $this->cityAllow) && !in_array('*', $this->cityAllow)) {
                    continue;
                }

                $city = $this->city($region, $country, $state, $default);

                if (!isset($state->cities)) {
                    continue;
                }

                foreach ($state->cities as $area) {
                    if (!in_array(data_get($area, 'name'), $this->areaAllow) && !in_array('*', $this->areaAllow)) {
                        continue;
                    }

                    $this->area($region, $country, $city, $area, $default);
                }
            }
        }
    }

    private function region(object $item, string $default, string $title): Region
    {
        $region = Region::whereHas('translation', function ($q) use ($default, $title) {
            $q->where('locale', $default)->where('title', $title);
        })->first();

        if (!$region) {
            $region = Region::create(['active' => true]);
            $region->translations()->create(['title' => $title, 'locale' => $default]);
            $this->command->info("region: $title");
        }

        return $region;
    }

    private function country(Region $region, object $item, string $default): Country
    {
        $name = data_get($item, 'name');

        $country = Country::whereHas('translation', function ($q) use ($default, $name) {
            $q->where('locale', $default)->where('title', $name);
        })->first();

        if (!$country) {
            $iso2 = Str::lower(data_get($item, 'iso2'));
            $country = Country::create([
                'region_id' => $region->id,
                'code' => $iso2,
                'active' => true,
                'img' => "https://flagcdn.com/h120/$iso2.png",
            ]);
            $country->translations()->create(['title' => $name, 'locale' => $default]);
            $this->command->info("country: $name");
        }

        return $country;
    }

    private function city(Region $region, Country $country, object $state, string $default): City
    {
        $name = data_get($state, 'name');

        $city = City::whereHas('translation', function ($q) use ($default, $name) {
            $q->where('locale', $default)->where('title', $name);
        })->first();

        if (!$city) {
            $city = City::create([
                'region_id' => $region->id,
                'country_id' => $country->id,
                'active' => true,
            ]);
            $city->translations()->create(['title' => $name, 'locale' => $default]);
            $this->command->info("state (as city): $name");
        }

        return $city;
    }

    private function area(Region $region, Country $country, City $city, object $area, string $default): void
    {
        $name = data_get($area, 'name');

        $exists = Area::whereHas('translation', function ($q) use ($default, $name) {
            $q->where('locale', $default)->where('title', $name);
        })->exists();

        if (!$exists) {
            $newArea = Area::create([
                'region_id' => $region->id,
                'country_id' => $country->id,
                'city_id' => $city->id,
                'active' => true,
            ]);
            $newArea->translations()->create(['title' => $name, 'locale' => $default]);
            $this->command->info("city (as area): $name");
        }
    }
}
