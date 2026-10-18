<?php
namespace App\Controllers\PublicSite;
use App\Controllers\BaseController;
class PrivacyController extends BaseController
{
    public function index(): string {return $this->render('public/privacy',['title'=>'Privacy notice']);}
}
