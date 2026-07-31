<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ReviewModel;

class ReviewApiController extends BaseController
{
    public function create()
    {
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $orderId = (int) ($payload['order_id'] ?? 0);

        if (! (new OrderModel())->where([
            'id' => $orderId,
            'customer_id' => $userId,
            'status' => 'delivered',
        ])->first()) {
            return $this->jsonError('Only delivered orders can be reviewed.');
        }

        $model = new ReviewModel();
        if ($model->where(['order_id' => $orderId, 'customer_id' => $userId])->first()) {
            return $this->jsonError('This order has already been reviewed.');
        }

        $productId = (int) ($payload['product_id'] ?? 0);
        if ($productId > 0 && ! (new OrderItemModel())->where(['order_id' => $orderId, 'product_id' => $productId])->first()) {
            return $this->jsonError('The selected product is not part of this order.');
        }

        $id = $model->insert([
            'order_id' => $orderId,
            'product_id' => $productId ?: null,
            'customer_id' => $userId,
            'rating' => (int) ($payload['rating'] ?? 0),
            'comment' => trim((string) ($payload['comment'] ?? '')),
            'is_visible' => 1,
        ], true);

        return $id
            ? $this->jsonSuccess('Review saved.', $model->find($id), 201)
            : $this->jsonError('Review validation failed.', $model->errors());
    }
}
