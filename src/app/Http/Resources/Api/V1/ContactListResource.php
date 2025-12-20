<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactListResource extends JsonResource
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
            'name' => $this->name,
            'type' => $this->type,
            'description' => $this->description,
            'is_public' => $this->is_public,
            'timezone' => $this->timezone,
            'subscribers_count' => $this->when(
                $this->subscribers_count !== null,
                $this->subscribers_count
            ),
            'group' => $this->when(
                $this->relationLoaded('group'),
                fn () => [
                    'id' => $this->group?->id,
                    'name' => $this->group?->name,
                ]
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
