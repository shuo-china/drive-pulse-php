<?php

namespace app\wxapp\controller;

class NoticeController extends BaseController
{
    public function index()
    {
        $this->success(200, config('sys.drive_pulse.notice'));
    }
}