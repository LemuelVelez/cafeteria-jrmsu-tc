<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\MediaStorageService;
use Throwable;

class UserController extends BaseController
{
    public function index(): string
    {
        return $this->render('admin/users/index', [
            'users' => (new UserModel())->orderBy('created_at', 'DESC')->findAll(),
        ]);
    }

    public function save()
    {
        if (! $this->validate([
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[160]',
            'phone' => 'permit_empty|max_length[30]',
            'password' => 'required|min_length[8]',
        ])) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new UserModel();
        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ($model->findByEmail($email)) {
            return redirect()->back()->withInput()->with('error', 'That email address is already registered.');
        }

        $avatarPath = null;
        $avatar = $this->request->getFile('avatar');
        $media = new MediaStorageService();
        if ($avatar && $avatar->getError() !== UPLOAD_ERR_NO_FILE) {
            $maxKb = (int) env('UPLOAD_MAX_SIZE_MB', 5) * 1024;
            if (! $avatar->isValid() || $avatar->hasMoved() || ! $this->validate(['avatar' => "is_image[avatar]|mime_in[avatar,image/png,image/jpeg,image/webp]|max_size[avatar,{$maxKb}]|max_dims[avatar,2400,2400]"])) {
                return redirect()->back()->withInput()->with('errors', $this->validator?->getErrors() ?: ['avatar' => 'The avatar image is invalid.']);
            }

            try {
                $avatarPath = $media->store($avatar, 'avatars');
            } catch (Throwable $exception) {
                log_message('error', 'Administrator avatar upload failed: {message}', ['message' => $exception->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'The avatar could not be uploaded. Check the media storage configuration.');
            }
        }

        $created = $model->insert([
            'name' => trim((string) $this->request->getPost('name')),
            'email' => $email,
            'phone' => trim((string) $this->request->getPost('phone')),
            'avatar' => $avatarPath,
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
            'role' => 'admin',
            'status' => 'active',
        ]);

        if (! $created) {
            $media->delete($avatarPath);
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        return redirect()->back()->with('success', 'Administrator account created.');
    }

    public function status(int $id)
    {
        $status = (string) $this->request->getPost('status');
        if (! in_array($status, ['active', 'inactive', 'banned'], true)) {
            return redirect()->back()->with('error', 'Invalid account status.');
        }

        $currentUserId = (int) (session()->get('user')['id'] ?? 0);
        if ($id === $currentUserId) {
            return redirect()->back()->with('error', 'You cannot change the status of your own account.');
        }

        $model = new UserModel();
        if (! $model->find($id)) {
            return redirect()->back()->with('error', 'User account not found.');
        }
        if (! $model->update($id, ['status' => $status])) {
            return redirect()->back()->with('errors', $model->errors());
        }

        return redirect()->back()->with('success', 'User status updated.');
    }
}
