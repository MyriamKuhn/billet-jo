<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'qr_code_link' => $this->qr_code_link,
            'pdf_link'     => $this->pdf_link,
            'is_used'      => $this->is_used,
            'is_refunded'  => $this->is_refunded,
            // autres champs si besoin...
        ];
    }
}
