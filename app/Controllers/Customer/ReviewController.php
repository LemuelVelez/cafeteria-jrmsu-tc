<?php

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Models\OrderModel;
use App\Models\ReviewModel;

class ReviewController extends BaseController
{
    public function index(): string
    {
        $userId = (int) (session()->get('user')['id'] ?? null);
        return $this->render('customer/reviews/index', [
            'orders' => (new OrderModel())->where(['customer_id' => $userId, 'status' => 'delivered'])->orderBy('created_at', 'DESC')->findAll(),
            'reviews' => (new ReviewModel())->where('customer_id', $userId)->orderBy('created_at', 'DESC')->findAll(),
        ]);
    }

    public function save()
    {
        $userId = (int) (session()->get('user')['id'] ?? null);
        $orderId = (int) $this->request->getPost('order_id');
        $order = (new OrderModel())->where(['id' => $orderId, 'customer_id' => $userId, 'status' => 'delivered'])->first();
        if (! $order) {
            return redirect()->back()->with('error', 'Only delivered orders can be reviewed.');
        }
        $model = new ReviewModel();
        $ok = $model->insert([
            'order_id' => $orderId,
            'product_id' => $this->request->getPost('product_id') ?: null,
            'customer_id' => $userId,
            'rating' => (int) $this->request->getPost('rating'),
            'comment' => trim((string) $this->request->getPost('comment')),
            'is_visible' => 1,
        ]);
        return $ok ? redirect()->back()->with('success', 'Thank you for your review.') : redirect()->back()->withInput()->with('errors', $model->errors());
    }
}
