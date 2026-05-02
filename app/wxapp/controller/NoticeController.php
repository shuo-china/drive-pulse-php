<?php

namespace app\wxapp\controller;

use app\wxapp\model\Notice;

class NoticeController extends BaseController
{
    public function index()
    {
        $notice = Notice::where('id', '<>', 0)->find();
        $this->success(200, $notice);
    }
}