<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ReviewModel;
use Throwable;

class ReviewController extends BaseController
{
    public function index(): string
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $reviewModel = new ReviewModel();
        $reviews = $reviewModel->where('customer_id', $userId)->orderBy('created_at', 'DESC')->findAll();
        $reviewedOrderIds = array_map('intval', array_column($reviews, 'order_id'));

        $orderModel = (new OrderModel())
            ->where(['customer_id' => $userId, 'status' => 'completed'])
            ->orderBy('created_at', 'DESC');
        if ($reviewedOrderIds !== []) {
            $orderModel->whereNotIn('id', $reviewedOrderIds);
        }

        return $this->render('customer/reviews/index', [
            'orders' => $orderModel->findAll(),
            'reviews' => $reviews,
        ]);
    }

    public function save()
    {
        $userId = (int) (session()->get('user')['id'] ?? 0);
        $orderId = (int) $this->request->getPost('order_id');
        $order = (new OrderModel())->where([
            'id' => $orderId,
            'customer_id' => $userId,
            'status' => 'completed',
        ])->first();

        if (! $order) {
            return redirect()->to('/customer/reviews')->with('error', 'Only completed orders can be reviewed.');
        }

        $model = new ReviewModel();
        if ($model->where(['order_id' => $orderId, 'customer_id' => $userId])->first()) {
            return redirect()->to('/customer/reviews')->with('error', 'This order has already been reviewed.');
        }

        $productIdInput = $this->request->getPost('product_id');
        $productId = null;
        if ($productIdInput !== null && $productIdInput !== '') {
            $validatedProductId = filter_var($productIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($validatedProductId === false) {
                return redirect()->to('/customer/reviews')->withInput()->with('error', 'The selected product is invalid.');
            }
            $productId = (int) $validatedProductId;
        }
        if ($productId !== null && ! (new OrderItemModel())->where(['order_id' => $orderId, 'product_id' => $productId])->first()) {
            return redirect()->to('/customer/reviews')->withInput()->with('error', 'The selected product is not part of this order.');
        }

        try {
            $ok = $model->insert([
                'order_id' => $orderId,
                'product_id' => $productId,
                'customer_id' => $userId,
                'rating' => $this->request->getPost('rating'),
                'comment' => trim((string) $this->request->getPost('comment')),
                'is_visible' => 1,
            ]);
        } catch (Throwable $exception) {
            if ($model->where(['order_id' => $orderId, 'customer_id' => $userId])->first()) {
                return redirect()->to('/customer/reviews')->with('error', 'This order has already been reviewed.');
            }
            log_message('error', 'Review creation failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->to('/customer/reviews')->withInput()->with('error', 'The review could not be saved right now.');
        }

        return $ok
            ? redirect()->to('/customer/reviews')->with('success', 'Thank you for your review.')
            : redirect()->to('/customer/reviews')->withInput()->with('errors', $model->errors());
    }
}
