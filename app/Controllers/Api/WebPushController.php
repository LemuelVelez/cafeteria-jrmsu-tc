<?php
namespace App\Controllers\Api;
use App\Controllers\BaseController;
use App\Services\WebPushService;
class WebPushController extends BaseController
{
    public function config()
    {
        $service=new WebPushService();
        if(!$service->enabled())return $this->jsonSuccess('Push notifications are disabled.',['enabled'=>false]);
        try {return $this->jsonSuccess('Push is available.',['enabled'=>true,'publicKey'=>$service->publicKey()]);}
        catch(\Throwable $e){return $this->jsonError('Push configuration is not available.',null,503);}
    }
    public function subscribe()
    {
        try {
            $input=$this->request->getJSON(true);
            if(!is_array($input))throw new \DomainException('Invalid subscription.');
            (new WebPushService())->subscribe((int)session()->get('user')['id'],$input);
            return $this->jsonSuccess('Push notifications enabled for this device.');
        }catch(\DomainException $e){return $this->jsonError($e->getMessage(),null,422);}
        catch(\Throwable $e){return $this->jsonError('Could not subscribe this device.',null,500);}
    }
    public function unsubscribe()
    {
        $input=$this->request->getJSON(true);
        if(!is_array($input) || !is_string($input['endpoint']??null))return $this->jsonError('Subscription endpoint is required.',null,422);
        (new WebPushService())->unsubscribe((int)session()->get('user')['id'],$input['endpoint']);
        return $this->jsonSuccess('Push subscription removed.');
    }
}
