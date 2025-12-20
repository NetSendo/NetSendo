<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'status' => $this->status,
            'contact_list_id' => $this->contact_list_id,
            'device' => $this->device,
            'ip_address' => $this->ip_address,
            'source' => $this->source,
            'opens_count' => $this->opens_count,
            'clicks_count' => $this->clicks_count,
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'custom_fields' => $this->when(
                $this->relationLoaded('fieldValues'),
                fn () => $this->getAllPlaceholderValues()
            ),
            'subscribed_at' => $this->subscribed_at?->toISOString(),
            'confirmed_at' => $this->confirmed_at?->toISOString(),
            'last_opened_at' => $this->last_opened_at?->toISOString(),
            'last_clicked_at' => $this->last_clicked_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
