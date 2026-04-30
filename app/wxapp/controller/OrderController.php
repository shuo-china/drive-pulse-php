<?php

namespace app\wxapp\controller;

use app\wxapp\model\User;
use app\wxapp\model\Order;

class OrderController extends BaseController
{
    public function pagination()
    {
        $orders = Order::with(['user', 'target_user'])->paginate();
        $this->success(200, $orders);
    }

    public function create()
    {
        $post = $this->request->post();
        $targetUser = User::where('uid', $post['uid'])->find();

        Order::create([
            'user_id' => $this->request->userId,
            'target_user_id' => $targetUser['id'],
            'channel_id' => $post['channel_id'],
            'count' => $post['count'],
        ]);

        $this->success(201);
    }
}