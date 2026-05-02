<?php

namespace app\admin\controller;

use app\admin\model\Notice;

class NoticeController extends BaseController
{
    public function detail()
    {
        $notice = Notice::order('id', 'desc')->find();
        $this->success(200, $notice);
    }

    public function update()
    {
        $post = $this->request->post();
        $notice = Notice::order('id', 'desc')->find();
        $this->success(200, $notice);
        if (!$notice) {
            Notice::create($post);
        } else {
            $notice->title = $post['title'];
            $notice->content = $post['content'];
            $notice->save();
        }

        $this->success(201);
    }
}