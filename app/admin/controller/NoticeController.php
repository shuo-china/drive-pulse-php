<?php

namespace app\admin\controller;

use app\admin\model\Notice;

class NoticeController extends BaseController
{
    public function detail()
    {
        $notice = Notice::find();
        $this->success(200, $notice);
    }

    public function update()
    {
        $post = $this->request->post();
        $notice = Notice::find();
        $this->succeess(200, $notice);
        if (!$notice) {
            Notice::create($post);
        } else {
            $notice->save($post);
        }

        $this->success(201);
    }
}