<?php

namespace app\wxapp\controller;

use app\admin\model\UserChannel;

class NoticeController extends BaseController
{
    public function index()
    {
        if (empty($this->request->userId)) {
            $this->error(400, '没有权限', 'NO_AUTH');
        }

        $userChannel = UserChannel::where('audit_status', 2)->where('user_id', $this->request->userId)->find();
        if (!$userChannel) {
            $this->error(400, '没有权限', 'NO_AUTH');
        }

        $this->success(200, [
            'title' => config('sys.drive_pulse.notice_title'),
            'content' => config('sys.drive_pulse.notice_content')
        ]);
    }
}
