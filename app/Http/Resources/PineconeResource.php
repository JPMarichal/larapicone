<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PineconeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'score' => $this->score ?? null,
            'values' => $this->values ?? null,
            'metadata' => $this->metadata ?? null,
            'created_at' => $this->created_at ?? null,
            'updated_at' => $this->updated_at ?? null,
        ];
    }
}
