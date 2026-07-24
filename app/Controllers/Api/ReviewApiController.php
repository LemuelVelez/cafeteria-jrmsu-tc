<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Models\ReviewModel;

class ReviewApiController extends BaseController
{
    public function create()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $userId = (int) (session()->get('user')['id'] ?? null);
        $orderId = (int) ($payload['order_id'] ?? 0);
        if (! (new OrderModel())->where(['id' => $orderId, 'customer_id' => $userId, 'status' => 'delivered'])->first()) {
            return $this->jsonError('Only delivered orders can be reviewed.');
        }
        $model = new ReviewModel();
        $id = $model->insert([
            'order_id' => $orderId,
            'product_id' => $payload['product_id'] ?? null,
            'customer_id' => $userId,
            'rating' => (int) ($payload['rating'] ?? 0),
            'comment' => trim((string) ($payload['comment'] ?? '')),
            'is_visible' => 1,
        ], true);
        return $id ? $this->jsonSuccess('Review saved.', $model->find($id), 201) : $this->jsonError('Review validation failed.', $model->errors());
    }
}
