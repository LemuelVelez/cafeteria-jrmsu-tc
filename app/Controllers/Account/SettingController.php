<?php

namespace App\Controllers\Account;

use App\Controllers\BaseController;
use App\Models\UserModel;
use App\Services\MediaStorageService;
use CodeIgniter\Exceptions\PageNotFoundException;
use Throwable;

class SettingController extends BaseController
{
    public function index(): string
    {
        $user = $this->currentUser();

        return $this->render('account/settings', [
            'title' => 'My settings',
            'profile' => $user,
        ]);
    }

    public function saveProfile()
    {
        $user = $this->currentUser();
        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'email' => 'required|valid_email|max_length[160]',
            'phone' => 'permit_empty|max_length[30]',
            'address' => 'permit_empty|max_length[1000]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/settings')->withInput()->with('errors', $this->validator->getErrors());
        }

        $model = new UserModel();
        $email = strtolower(trim((string) $this->request->getPost('email')));
        $emailOwner = $model->findByEmail($email);
        if ($emailOwner && (int) $emailOwner['id'] !== (int) $user['id']) {
            return redirect()->to('/settings')->withInput()->with('error', 'That email address is already registered.');
        }

        $avatarPath = $user['avatar'] ?? null;
        $newAvatarPath = null;
        $avatar = $this->request->getFile('avatar');
        $media = new MediaStorageService();

        if ($avatar && $avatar->getError() !== UPLOAD_ERR_NO_FILE) {
            $maxKb = (int) env('UPLOAD_MAX_SIZE_MB', 5) * 1024;
            $avatarRule = "is_image[avatar]|mime_in[avatar,image/png,image/jpeg,image/webp]|max_size[avatar,{$maxKb}]|max_dims[avatar,2400,2400]";
            if (! $avatar->isValid() || $avatar->hasMoved() || ! $this->validate(['avatar' => $avatarRule])) {
                return redirect()->to('/settings')->withInput()->with('errors', $this->validator?->getErrors() ?: ['avatar' => 'The avatar image is invalid.']);
            }

            try {
                $newAvatarPath = $media->store($avatar, 'avatars');
                $avatarPath = $newAvatarPath;
            } catch (Throwable $exception) {
                log_message('error', 'Profile avatar upload failed for user {userId}: {message}', [
                    'userId' => $user['id'],
                    'message' => $exception->getMessage(),
                ]);

                return redirect()->to('/settings')->withInput()->with('error', 'The avatar could not be uploaded. Check the media storage configuration.');
            }
        }

        $data = [
            'name' => trim((string) $this->request->getPost('name')),
            'email' => $email,
            'phone' => trim((string) $this->request->getPost('phone')),
            'address' => trim((string) $this->request->getPost('address')),
            'avatar' => $avatarPath,
        ];

        try {
            if (! $model->update((int) $user['id'], $data)) {
                $media->delete($newAvatarPath);

                return redirect()->to('/settings')->withInput()->with('errors', $model->errors());
            }
        } catch (Throwable $exception) {
            $media->delete($newAvatarPath);
            log_message('error', 'Profile update failed for user {userId}: {message}', [
                'userId' => $user['id'],
                'message' => $exception->getMessage(),
            ]);

            return redirect()->to('/settings')->withInput()->with('error', 'The profile could not be updated.');
        }

        if ($newAvatarPath !== null && ! empty($user['avatar'])) {
            $media->delete((string) $user['avatar']);
        }

        $this->refreshSessionUser(array_merge($user, $data));

        return redirect()->to('/settings')->with('success', 'Profile settings updated.');
    }

    public function removeAvatar()
    {
        $user = $this->currentUser();
        if (empty($user['avatar'])) {
            return redirect()->to('/settings')->with('success', 'The default cafeteria logo is already in use.');
        }

        $model = new UserModel();
        if (! $model->update((int) $user['id'], ['avatar' => null])) {
            return redirect()->to('/settings')->with('errors', $model->errors());
        }

        (new MediaStorageService())->delete((string) $user['avatar']);
        $user['avatar'] = null;
        $this->refreshSessionUser($user);

        return redirect()->to('/settings')->with('success', 'Profile photo removed. The cafeteria logo is now your default avatar.');
    }

    public function savePassword()
    {
        $user = $this->currentUser();
        $rules = [
            'current_password' => 'required',
            'password' => 'required|min_length[8]',
            'password_confirm' => 'required|matches[password]',
        ];

        if (! $this->validate($rules)) {
            return redirect()->to('/settings')->with('errors', $this->validator->getErrors());
        }

        if (! password_verify((string) $this->request->getPost('current_password'), (string) $user['password_hash'])) {
            return redirect()->to('/settings')->with('error', 'The current password is incorrect.');
        }

        $model = new UserModel();
        if (! $model->update((int) $user['id'], [
            'password_hash' => password_hash((string) $this->request->getPost('password'), PASSWORD_DEFAULT),
        ])) {
            return redirect()->to('/settings')->with('errors', $model->errors());
        }

        return redirect()->to('/settings')->with('success', 'Password updated.');
    }

    private function currentUser(): array
    {
        $id = (int) ($this->session->get('user')['id'] ?? 0);
        $user = $id > 0 ? (new UserModel())->find($id) : null;
        if (! $user) {
            throw PageNotFoundException::forPageNotFound('User account not found.');
        }

        return $user;
    }

    private function refreshSessionUser(array $user): void
    {
        $sessionUser = (array) $this->session->get('user');
        $this->session->set('user', array_merge($sessionUser, [
            'id' => (int) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'avatar' => $user['avatar'] ?? null,
        ]));
    }
}
