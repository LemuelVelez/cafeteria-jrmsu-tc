<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\PromoService;

class PromoApiController extends BaseController
{
    public function apply()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        try {
            return $this->jsonSuccess('Promo applied.', (new PromoService())->calculate((string) ($payload['code'] ?? ''), (float) ($payload['subtotal'] ?? 0)));
        } catch (\Throwable $e) {
            return $this->jsonError($e->getMessage());
        }
    }
}
