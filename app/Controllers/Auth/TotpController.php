<?php
namespace App\Controllers\Auth;
use App\Controllers\BaseController;
use App\Services\TotpService;
use App\Services\FieldEncryption;
use App\Services\AuditLogService;
use App\Models\UserModel;
class TotpController extends BaseController
{
    public function challenge(): string {return $this->render('auth/totp',['title'=>'Two-factor verification']);}
    public function confirmChallenge()
    {
        $id=(int)session()->get('user')['id'];
        if (!service('throttler')->check('totp-'. $id,5,MINUTE))return redirect()->to('/two-factor')->with('error','Too many attempts.');
        $user=(new UserModel())->find($id);
        if (!$user)return redirect()->to('/login');
        $code=trim((string)$this->request->getPost('code'));
        $secret=(string)($user['totp_secret']??'');
        $unreadable=$secret===FieldEncryption::UNREADABLE_TOTP_SECRET;
        $valid=$secret!=='' && !$unreadable && TotpService::verify($secret,$code);
        // Recovery codes are stored as one-way hashes and remain usable even if
        // the encrypted TOTP secret cannot be decrypted with the current key.
        if (!$valid && $secret!=='' && strlen($code)>=10)$valid=TotpService::consumeRecovery($id,$code);
        if (!$valid)return redirect()->to('/two-factor')->with('error', $unreadable
            ? 'Authenticator secret is unavailable. Restore the original encryption key or enter a recovery code.'
            : 'Incorrect verification code.');
        session()->regenerate(true);session()->set('totp_verified',true);
        (new AuditLogService())->record('two_factor_success','user',$id);
        return redirect()->to(role_home($user['role']))->with('success','Verification successful.');
    }
    public function start()
    {
        $id=(int)session()->get('user')['id'];$user=(new UserModel())->find($id);
        if (!$user)return redirect()->to('/login');
        if (!empty($user['totp_secret']))return redirect()->to('/settings')->with('error','Two-factor authentication is already enabled.');
        $secret=TotpService::generateSecret();
        session()->set('totp_pending_secret',$secret);
        return redirect()->to('/settings')->with('success','Enter this secret in your authenticator and confirm a six-digit code.');
    }
    public function confirmSetup()
    {
        $secret=(string)session()->get('totp_pending_secret');$id=(int)session()->get('user')['id'];
        if ($secret==='' || !TotpService::verify($secret,(string)$this->request->getPost('code')))
            return redirect()->to('/settings')->with('error','Invalid authenticator code.');
        $codes=TotpService::recoveryCodes();
        $db=db_connect();$db->transBegin();
        try {
            $locked=$db->query('SELECT id,totp_secret FROM users WHERE id=? FOR UPDATE',[$id])->getRowArray();
            if (!$locked||!empty($locked['totp_secret']))throw new \DomainException('Two-factor already configured.');
            $db->table('users')->where('id',$id)->update(['totp_secret'=>FieldEncryption::seal($secret),'totp_recovery_hashes'=>json_encode(array_map('password_hash',$codes,array_fill(0,count($codes),PASSWORD_BCRYPT)))]);
            (new AuditLogService($db))->record('two_factor_enabled','user',$id);
            if (!$db->transStatus())throw new \RuntimeException('Unable to save authenticator.');
            $db->transCommit();session()->remove('totp_pending_secret');session()->regenerate(true);session()->set('totp_verified',true);
            return redirect()->to('/settings')->with('success','Two-factor enabled. Save these recovery codes now: '.implode(' · ',$codes));
        }catch(\Throwable $e){$db->transRollback();return redirect()->to('/settings')->with('error',$e->getMessage());}
    }
}
