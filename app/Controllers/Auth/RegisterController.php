<?php

namespace App\Controllers\Auth;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\MediaStorageService;
use Throwable;

class RegisterController extends BaseController
{
    public function index(): string
    {
        return $this->render('auth/register');
    }

    public function store()
    {
        $key = 'register-' . hash('sha256', $this->request->getIPAddress());
        if (! service('throttler')->check($key, 3, MINUTE)) {
            return redirect()->back()->withInput()->with('error', 'Too many registration attempts. Please try again in one minute.');
        }

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[160]',
            'phone' => 'permit_empty|max_length[30]',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];
        if (! $this->validate($rules)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $email = strtolower(trim((string) $this->request->getPost('email')));
        if ((new UserModel())->findByEmail($email)) {
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
                log_message('error', 'Customer avatar upload failed: {message}', ['message' => $exception->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'The avatar could not be uploaded. Check the media storage configuration.');
            }
        }

        $id = (new AuthService())->registerCustomer([
            'name' => $this->request->getPost('name'),
            'email' => $email,
            'phone' => $this->request->getPost('phone'),
            'address' => $this->request->getPost('address'),
            'avatar' => $avatarPath,
            'password' => $this->request->getPost('password'),
        ]);
        if (! $id) {
            $media->delete($avatarPath);
            return redirect()->back()->withInput()->with('error', 'Unable to create the account.');
        }

        return redirect()->to('/login')->with('success', 'Account created. You may now sign in.');
    }
}
