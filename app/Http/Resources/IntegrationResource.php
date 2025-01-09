<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IntegrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $title = '';
        switch($this->type) {
            case 'google_search_console':
                $title = 'Google Search Console';
                break;
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $title,
            'status' => $this->status,
            'created_at' => $this->created_at->format('m/d/Y'),
        ];
    }
}
