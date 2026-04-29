<?php

namespace app\admin\controller;

use app\admin\model\User;
use app\admin\model\Channel;
use app\admin\model\UserChannel;
use app\admin\model\Order;

class UserController extends BaseController
{
    public function pagination()
    {
        $param = $this->request->param();
        $map = [];
        if (!empty($param['nickname'])) {
            $map['nickname'] = ['like', '%' . $param['nickname'] . '%'];
        }
        if (!empty($param['uid'])) {
            $map['uid'] = $param['uid'];
        }

        $channels = Channel::where('status', 1)->field('id,title')->select();

        $users = User::where($map)->paginate()->each(function ($item) use ($channels) {
            $userChannels = UserChannel::where('user_id', $item->id)->column('audit_status', 'channel_id');
            $releaseCounts = Order::where('user_id', $item->id)
                ->field('channel_id, SUM(count) as release_count')
                ->group('channel_id')
                ->select()
                ->column('release_count', 'channel_id');

            $takeCounts = Order::where('target_user_id', $item->id)
                ->field('channel_id, SUM(count) as take_count')
                ->group('channel_id')
                ->select()
                ->column('take_count', 'channel_id');

            $channelList = [];
            foreach ($channels as $channel) {
                $channelList[] = [
                    'id' => $channel->id,
                    'title' => $channel->title,
                    'audit_status' => $userChannels[$channel->id] ?? 0,
                    'balance_count' => ($releaseCounts[$channel->id] ?? 0) - ($takeCounts[$channel->id] ?? 0)
                ];
            }
            $item->channels = $channelList;

            return $item;
        });

        $this->success(200, $users);
    }

    public function update()
    {
        $post = $this->request->post();
        User::update($post);
        $this->success(201);
    }
}
