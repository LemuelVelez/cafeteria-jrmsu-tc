<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\UserModel;
use App\Services\OrderService;
use CodeIgniter\Exceptions\PageNotFoundException;
use DomainException;
use Throwable;

class OrderController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/orders/index', [
            'orders' => (new OrderModel())->detailed(),
            'riders' => (new UserModel())->activeRiders(),
        ]);
    }

    public function show(int $id): string
    {
        $orders = (new OrderModel())->detailed(['orders.id' => $id]);
        $order = $orders[0] ?? null;
        if (! $order) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->render('admin/orders/show', [
            'order' => $order,
            'items' => (new OrderItemModel())->where('order_id', $id)->findAll(),
            'riders' => (new UserModel())->activeRiders(),
            'statusOptions' => OrderService::allowedTransitions($order, (array) session()->get('user')),
            'canAssignRider' => $order['order_type'] === 'delivery'
                && ! in_array($order['status'], ['out_for_delivery', 'completed', 'cancelled'], true),
        ]);
    }

    public function status(int $id)
    {
        try {
            (new OrderService())->updateStatus(
                $id,
                (string) $this->request->getPost('status'),
                session()->get('user'),
                (string) $this->request->getPost('note'),
            );

            return redirect()->to('/admin/orders/' . $id)->with('success', 'Order status updated.');
        } catch (DomainException $exception) {
            return redirect()->to('/admin/orders/' . $id)->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Admin order status update failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->to('/admin/orders/' . $id)->with('error', 'The order status could not be updated right now.');
        }
    }

    public function assignRider(int $id)
    {
        try {
            (new OrderService())->assignRider(
                $id,
                (int) $this->request->getPost('rider_id'),
                session()->get('user'),
            );

            return redirect()->to('/admin/orders/' . $id)->with('success', 'Rider assigned.');
        } catch (DomainException $exception) {
            return redirect()->to('/admin/orders/' . $id)->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Admin rider assignment failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->to('/admin/orders/' . $id)->with('error', 'The rider could not be assigned right now.');
        }
    }
}
