<?php

namespace app\admin\controller;

use app\admin\model\User;
use app\admin\model\Channel;
use app\admin\model\UserChannel;
use app\admin\model\Order;
use app\admin\model\File as FileModel;
use think\facade\Db;

class UserController extends BaseController
{
    public function pagination()
    {
        $param = $this->request->param();
        $map = [];

        if (!$this->request->clientInfo['is_top']) {
            $map[] = ['is_hidden', '=', 0];
        }

        if (!empty($param['nickname'])) {
            $map[] = ['nickname', 'like', '%' . $param['nickname'] . '%'];
        }
        if (!empty($param['uid'])) {
            $map[] = ['uid', '=', $param['uid']];
        }
        if (isset($param['min_balance_count']) && $param['min_balance_count'] !== '') {
            $minBalanceCount = (int) $param['min_balance_count'];
            $orderTable = (new Order())->getTable();
            $userTable = (new User())->getTable();

            $rows = Db::query(
                "SELECT u.id
                FROM {$userTable} u
                INNER JOIN (
                    SELECT t.user_id, t.channel_id, SUM(t.delta) AS channel_delta
                    FROM (
                        SELECT user_id, channel_id, `count` AS delta
                        FROM {$orderTable}
                        WHERE delete_time IS NULL
                        UNION ALL
                        SELECT target_user_id AS user_id, channel_id, -`count` AS delta
                        FROM {$orderTable}
                        WHERE delete_time IS NULL
                    ) t
                    GROUP BY t.user_id, t.channel_id
                ) d ON d.user_id = u.id
                GROUP BY u.id, u.initial_balance
                HAVING MAX(u.initial_balance + IFNULL(d.channel_delta, 0)) >= :min_balance_count",
                ['min_balance_count' => $minBalanceCount]
            );

            $allowUserIds = array_column($rows, 'id');
            if (empty($allowUserIds)) {
                $map[] = ['id', '=', 0];
            } else {
                $map[] = ['id', 'in', $allowUserIds];
            }
        }
        if (isset($param['max_balance_count']) && $param['max_balance_count'] !== '') {
            $maxBalanceCount = (int) $param['max_balance_count'];
            $orderTable = (new Order())->getTable();
            $userTable = (new User())->getTable();

            $rows = Db::query(
                "SELECT u.id
                FROM {$userTable} u
                INNER JOIN (
                    SELECT t.user_id, t.channel_id, SUM(t.delta) AS channel_delta
                    FROM (
                        SELECT user_id, channel_id, `count` AS delta
                        FROM {$orderTable}
                        WHERE delete_time IS NULL
                        UNION ALL
                        SELECT target_user_id AS user_id, channel_id, -`count` AS delta
                        FROM {$orderTable}
                        WHERE delete_time IS NULL
                    ) t
                    GROUP BY t.user_id, t.channel_id
                ) d ON d.user_id = u.id
                GROUP BY u.id, u.initial_balance
                HAVING MAX(u.initial_balance + IFNULL(d.channel_delta, 0)) <= :max_balance_count",
                ['max_balance_count' => $maxBalanceCount]
            );

            $allowUserIds = array_column($rows, 'id');
            if (empty($allowUserIds)) {
                $map[] = ['id', '=', 0];
            } else {
                $map[] = ['id', 'in', $allowUserIds];
            }
        }

        $users = User::where($map)->order('id', 'desc')->paginate();
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
                    'balance_count' => $item->initial_balance + ($releaseCountMap[$item->id][$channel['id']] ?? 0) - ($takeCountMap[$item->id][$channel['id']] ?? 0),
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
