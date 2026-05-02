<?php

namespace app\wxapp\controller;

use app\wxapp\model\Notice;

class NoticeController extends BaseController
{
    public function index()
    {
        $notices = Notice::order('id', 'desc')->select()->toArray();

        $this->success(200, empty($notices) ? null : $notices[0]);
    }
}