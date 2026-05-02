<?php

namespace app\admin\controller;

use app\admin\model\Notice;

class NoticeController extends BaseController
{
    public function update()
    {
        $post = $this->request->post();
        $notice = Notice::find();
        if (!$notice) {
            Notice::create($post);
        } else {
            $notice->save($post);
        }

        $this->success(201);
    }
}