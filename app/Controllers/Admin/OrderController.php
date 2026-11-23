<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\UserModel;
use App\Services\OrderService;
use App\Services\OrderCancellationService;
use CodeIgniter\Exceptions\PageNotFoundException;
use DomainException;
use Throwable;

class OrderController extends BaseController
{
    public function index(): string
    {
        $orders = (new OrderModel())->detailed();
        return $this->render('admin/orders/index', [
            'orders' => $orders,
            'pendingRequests' => (new OrderCancellationService())->pendingByOrderIds(array_column($orders, 'id')),
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

        $cancellationRequest = (new OrderCancellationService())->forOrder($id);
        $reviewer = ! empty($cancellationRequest['reviewed_by'])
            ? db_connect()->table('users')->select('name')->where('id', (int) $cancellationRequest['reviewed_by'])->get()->getRow('name')
            : null;
        return $this->render('admin/orders/show', [
            'order' => $order,
            'cancellationRequest' => $cancellationRequest,
            'cancellationReviewer' => $reviewer,
            'items' => (new OrderItemModel())->where('order_id', $id)->findAll(),
            'riders' => (new UserModel())->activeRiders(),
            'handoffs' => db_connect()->table('order_handoffs h')->select('h.*,u.name verifier_name')
                ->join('users u','u.id=h.verified_by','left')->where('order_id',$id)->get()->getResultArray(),
            'statusOptions' => OrderService::allowedTransitions($order, (array) session()->get('user')),
            'canAssignRider' => $order['order_type'] === 'delivery'
                && ! in_array($order['status'], ['out_for_delivery', 'delivered', 'cancelled'], true),
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
    public function approveCancellation(int $requestId)
    {
        $request = db_connect()->table('order_cancellation_requests')->where('id', $requestId)->get()->getRowArray();
        $orderId = (int) ($request['order_id'] ?? 0);
        try {
            (new OrderCancellationService())->approve($requestId, (array) session()->get('user'));
            return redirect()->to('/admin/orders/' . $orderId)->with('success', 'Cancellation approved.');
        } catch (DomainException $exception) {
            return redirect()->to('/admin/orders/' . $orderId)->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Admin cancellation approval failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->to('/admin/orders/' . $orderId)->with('error', 'Cancellation could not be approved right now.');
        }
    }

    public function rejectCancellation(int $requestId)
    {
        $request = db_connect()->table('order_cancellation_requests')->where('id', $requestId)->get()->getRowArray();
        $orderId = (int) ($request['order_id'] ?? 0);
        try {
            (new OrderCancellationService())->reject($requestId, (array) session()->get('user'), (string) $this->request->getPost('note'));
            return redirect()->to('/admin/orders/' . $orderId)->with('success', 'Cancellation request declined.');
        } catch (DomainException $exception) {
            return redirect()->to('/admin/orders/' . $orderId)->with('error', $exception->getMessage());
        } catch (Throwable $exception) {
            log_message('error', 'Admin cancellation rejection failed: {message}', ['message' => $exception->getMessage()]);
            return redirect()->to('/admin/orders/' . $orderId)->with('error', 'Cancellation request could not be declined right now.');
        }
    }

}
