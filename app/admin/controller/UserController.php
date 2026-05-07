<?php

namespace app\admin\controller;

use app\admin\model\User;
use app\admin\model\Channel;
use app\admin\model\UserChannel;
use app\admin\model\Order;
use app\admin\model\File as FileModel;

class UserController extends BaseController
{
    public function pagination()
    {
        $param = $this->request->param();
        $map = [];
        if (!empty($param['nickname'])) {
            $map[] = ['nickname', 'like', '%' . $param['nickname'] . '%'];
        }
        if (!empty($param['uid'])) {
            $map[] = ['uid', '=', $param['uid']];
        }

        $users = User::where($map)->paginate();
        $pageUserIds = array_column($users->items(), 'id');
        $releaseCountMap = [];
        $takeCountMap = [];
        $userChannelMap = [];

        if (!empty($pageUserIds)) {
            $releaseCountRows = Order::where('user_id', 'in', $pageUserIds)
                ->field('user_id, channel_id, SUM(count) as release_count_sum')
                ->group('user_id, channel_id')
                ->select()
                ->toArray();

            foreach ($releaseCountRows as $row) {
                $releaseCountMap[$row['user_id']][$row['channel_id']] = (int) $row['release_count_sum'];
            }

            $takeCountRows = Order::where('target_user_id', 'in', $pageUserIds)
                ->field('target_user_id, channel_id, SUM(count) as take_count_sum')
                ->group('target_user_id, channel_id')
                ->select()
                ->toArray();

            foreach ($takeCountRows as $row) {
                $takeCountMap[$row['target_user_id']][$row['channel_id']] = (int) $row['take_count_sum'];
            }

            $userChannelRows = UserChannel::where('user_id', 'in', $pageUserIds)
                ->field('user_id, channel_id, audit_status')
                ->select()
                ->toArray();

            foreach ($userChannelRows as $row) {
                $userChannelMap[$row['user_id']][$row['channel_id']] = (int) $row['audit_status'];
            }
        }

        $channels = Channel::where('status', 1)->field('id,title')->select()->toArray();
        $users->each(function ($item) use ($channels, $releaseCountMap, $takeCountMap, $userChannelMap) {
            $channelList = [];
            foreach ($channels as $channel) {
                $channelList[] = [
                    'id' => $channel['id'],
                    'title' => $channel['title'],
                    'audit_status' => $userChannelMap[$item->id][$channel['id']] ?? 0,
                    'balance_count' => ($releaseCountMap[$item->id][$channel['id']] ?? 0) - ($takeCountMap[$item->id][$channel['id']] ?? 0),
                ];
            }
            $item->channels = $channelList;

            return $item;
        });

        $this->success(200, $users);
    }

    public function detail($id)
    {
        $user = User::with('avatar')->where('id', $id)->find();
        $this->success(200, $user);
    }

    public function update()
    {
        $post = $this->request->post();

        $this->validate($post, 'User');

        if ($post['avatar_key']) {
            $avatar = FileModel::where('key', $post['avatar_key'])->find();
            $post['avatar_path'] = $avatar->getData('path');
        } else {
            $post['avatar_path'] = '';
        }

        User::update($post);
        $this->success(201);
    }
}
