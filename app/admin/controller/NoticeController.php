<?php

namespace app\admin\controller;

use app\admin\model\Notice;

class NoticeController extends BaseController
{
    public function detail()
    {
        $notices = Notice::order('id', 'desc')->select()->toArray();
        $this->success(200, empty($notices) ? null : $notices[0]);
    }

    public function update()
    {
        $post = $this->request->post();
        $notices = Notice::order('id', 'desc')->select()->toArray();

        $this->success(200, Notice::where('id', '<>', 0)->find());

        if (empty($notices)) {
            Notice::create($post);
        } else {
            Notice::where('id', $notices[0]['id'])->update($post);
        }

        $this->success(201);
    }
}