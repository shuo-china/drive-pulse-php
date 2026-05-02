<?php

namespace app\admin\controller;

use app\admin\model\Notice;

class NoticeController extends BaseController
{
    public function detail()
    {
        $notice = Notice::where('id', '<>', 0)->find();
        $this->success(200, $notice);
    }

    public function update()
    {
        $post = $this->request->post();
        $notice = Notice::where('id', '<>', 0)->find();

        if (!$notice) {
            Notice::create($post);
        } else {
            $notice->save($post);
        }

        $this->success(201);
    }
}