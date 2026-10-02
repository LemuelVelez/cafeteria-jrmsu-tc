<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ReviewModel;
use Throwable;

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
            'status' => 'completed',
        ])->first()) {
            return $this->jsonError('Only completed orders can be reviewed.');
        }

        $model = new ReviewModel();
        if ($model->where(['order_id' => $orderId, 'customer_id' => $userId])->first()) {
            return $this->jsonError('This order has already been reviewed.');
        }

        $productIdInput = $payload['product_id'] ?? null;
        $productId = null;
        if ($productIdInput !== null && $productIdInput !== '') {
            $validatedProductId = filter_var($productIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validatedProductId === false) {
                return $this->jsonError('The selected product is invalid.');
            }
            $productId = (int) $validatedProductId;
        }
        if ($productId !== null && ! (new OrderItemModel())->where(['order_id' => $orderId, 'product_id' => $productId])->first()) {
            return $this->jsonError('The selected product is not part of this order.');
        }

        try {
            $id = $model->insert([
                'order_id' => $orderId,
                'product_id' => $productId,
                'customer_id' => $userId,
                'rating' => $payload['rating'] ?? null,
                'comment' => trim((string) ($payload['comment'] ?? '')),
                'is_visible' => 1,
            ], true);
        } catch (Throwable $exception) {
            if ($model->where(['order_id' => $orderId, 'customer_id' => $userId])->first()) {
                return $this->jsonError('This order has already been reviewed.');
            }
            log_message('error', 'Review creation failed: {message}', ['message' => $exception->getMessage()]);
            return $this->jsonError('The review could not be saved right now.', null, 500);
        }

        return $id
            ? $this->jsonSuccess('Review saved.', $model->find($id), 201)
            : $this->jsonError('Review validation failed.', $model->errors());
    }
}
