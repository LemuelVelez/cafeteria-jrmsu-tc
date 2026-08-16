<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\PromoService;
use DomainException;
use Throwable;

class PromoApiController extends BaseController
{
    public function apply()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        try {
            return $this->jsonSuccess('Promo applied.', (new PromoService())->calculate((string) ($payload['code'] ?? ''), (float) ($payload['subtotal'] ?? 0)));
        } catch (DomainException $exception) {
            return $this->jsonError($exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Promo calculation failed: {message}', ['message' => $exception->getMessage()]);
            return $this->jsonError('The promo could not be checked right now.', null, 500);
        }
    }
}
