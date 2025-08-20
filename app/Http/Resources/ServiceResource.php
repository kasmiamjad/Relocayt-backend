<?php
declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Service;
use App\Models\ServiceMaster;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        /** @var Service|JsonResource $this */
        $locales = $this->relationLoaded('translations') ?
            $this->translations->pluck('locale')->toArray() : null;

        $img = $this->relationLoaded('galleries') ? $this->galleries?->whereNotNull('path')?->first()?->path : null;

        $masterSum = DB::table('service_masters')
            ->select(['price', 'commission_fee', 'discount'])
            ->where('service_id', $this->id)
            ->orderBy('price')
            ->orderBy('commission_fee')
            ->first();
        
            $radiusKm = $this->radius_km; // may be null
            $radiusM  = !is_null($radiusKm) ? (int) round($radiusKm * 1000) : null;

        $minPrice = 0;

        if (!empty($masterSum?->price)) {
            /** @var ServiceMaster $masterSum */
            $minPrice = ($masterSum->price + $masterSum->commission_fee - $masterSum->discount) * $this->currency();
        }

        return [
            'id'                => $this->when($this->id,                   $this->id),
            'slug'              => $this->when($this->slug,                 $this->slug),
            'category_id'       => $this->when($this->category_id,          $this->category_id),
            'shop_id'           => $this->when($this->shop_id,              $this->shop_id),
            'master_id'         => $this->when($this->master_id,            $this->master_id),
            'status'            => $this->when($this->status,               $this->status),
            'status_note'       => $this->when($this->status_note,          $this->status_note),
            'price'             => $this->when($this->rate_price,           $this->rate_price),
            'total_price'       => $this->when($this->rate_total_price,     $this->rate_total_price),
            'min_price'         => $minPrice,
            'interval'          => $this->when($this->interval,             $this->interval),
            'pause'             => $this->when($this->pause,                $this->pause),
            'type'              => $this->when($this->type,                 $this->type),
            'service_type' => $this->when($this->service_type, $this->service_type),
            'commission_fee'    => $this->when($this->rate_commission_fee,  $this->rate_commission_fee),
            'img'               => $this->when($img,                        $img),
            'data'              => $this->when($this->data,                 $this->data),
            'title' => $this->translations?->where('key', 'title')->pluck('value', 'locale')->toArray() ?? [],
            'description' => $this->translations?->where('key', 'description')->pluck('value', 'locale')->toArray() ?? [],
            'address'       => ['en' => $this->address],
            'street'        => $this->when($this->street, $this->street),
            'city'          => $this->when($this->city, $this->city),
            'state'         => $this->when($this->state, $this->state),
            'zipcode'       => $this->when($this->zipcode, $this->zipcode),
            'country'       => $this->when($this->country, $this->country),
            'content' => $this->when($this->content, $this->content),

            'lat_long' => [
                'latitude'  => $this->when($this->latitude, $this->latitude),
                'longitude' => $this->when($this->longitude, $this->longitude),
            ],

            'galleryImages' => $this->when($this->gallery, $this->gallery ?? []),
            'documents'     => $this->when($this->documents, $this->documents ?? []),

            'radius_km' => $this->when(!is_null($radiusKm), (float) $radiusKm),
            'radius_m'  => $this->when(!is_null($radiusM),  (int) $radiusM),

            'gender'            => $this->when($this->gender,               $this->gender),
            'created_at'        => $this->when($this->created_at,  $this->created_at?->format('Y-m-d H:i:s') . 'Z'),
            'updated_at'        => $this->when($this->updated_at,  $this->updated_at?->format('Y-m-d H:i:s') . 'Z'),

            // Relation
            'category'          => CategoryResource::make($this->whenLoaded('category')),
            'shop'              => ShopResource::make($this->whenLoaded('shop')),
            'translation'       => TranslationResource::make($this->whenLoaded('translation')),
            'translations'      => TranslationResource::collection($this->whenLoaded('translations')),
            'galleries'         => GalleryResource::collection($this->whenLoaded('galleries')),
            'service_master'    => ServiceMasterResource::make($this->whenLoaded('serviceMaster')),
            'service_masters'   => ServiceMasterResource::collection($this->whenLoaded('serviceMasters')),
            'service_extras'    => ServiceExtraResource::collection($this->whenLoaded('serviceExtras')),
            'service_faqs'      => ServiceFaqResource::collection($this->whenLoaded('serviceFaqs')),
            'locales'           => $this->when($locales, $locales),
        ];
    }
}
