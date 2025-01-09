<?php

namespace App\Http\Resources;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class AgencyMemberResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        if (!empty($this->first_name) || !empty($this->last_name)) {
            $username = $this->first_name . ' ' . $this->last_name;
        } else {
            $username = $this->name;
        }
        $isAgencyOwner = Agency::where('main_admin_id', $this->user_id)
            ->where('status', Agency::STATUS['active'])
            ->exists();

        $result = [
            'id' => $this->id,
            'name' => $this->name,
            'user' => [
                'id' => $this->user_id,
                'user' => $username,
                'email' => $this->email,
                'role' => $isAgencyOwner ? 'Agency owner' : 'Member'
            ],
            'is_main_admin' => $this->user_id == $this->main_admin_id,
            'created_at' => (new \DateTime($this->created_at))->format('m/d/Y'),
            'updated_at' => (new \DateTime($this->updated_at))->format('m/d/Y'),
        ];

        if (User::isAgencyOwner(\Auth::user())) {
            $result['invited_at'] =  (new \DateTime($this->invited_at))->format('m/d/Y');
        }

        return $result;
    }
}
