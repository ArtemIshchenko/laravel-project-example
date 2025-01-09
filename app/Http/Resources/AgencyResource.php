<?php

namespace App\Http\Resources;

use App\Models\AgencyMember;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class AgencyResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'main_admin' => [
                'id' => $this->admin->id,
                'user' => User::getName($this->admin),
                'email' => $this->admin->email
            ],
            'user_quantity' => $this->members()->where('status', AgencyMember::STATUS['active'])->count(),
            'created_at' => $this->created_at->format('m/d/Y'),
            'updated_at' => $this->updated_at->format('m/d/Y'),
            'status' => $this->status,
        ];
    }
}
