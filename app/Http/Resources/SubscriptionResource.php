<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
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
            'customer' => [
                'id' => $this->user->id,
                'email' => $this->user->email,
                'username' => User::getName($this->user),
            ],
            'product' => new ProductResource($this->product),
            'subscription_id' => $this->subscription_id,
            'sum' => $this->sum,
            'currency' => $this->currency,
            'begin' => (new \DateTime())->setTimestamp($this->current_period_start)->format('Y M, d g:i A'),
            'end' => (new \DateTime())->setTimestamp($this->current_period_end)->format('Y M, d g:i A'),
            'next_sum' => $this->next_sum,
        ];
    }
}
