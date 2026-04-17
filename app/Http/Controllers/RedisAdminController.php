<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Predis\Client;
use Predis\CommunicationException;

class RedisAdminController extends Controller
{
    /**
     * Create and configure a Redis client for the selected database.
     */
    private function getRedisClient(int $db = 0): Client
    {
        $client = new Client([
            'scheme'   => 'tcp',
            'host'     => config('database.redis.default.host', '127.0.0.1'),
            'port'     => config('database.redis.default.port', 6379),
            'password' => config('database.redis.default.password', null),
        ]);
        $client->connect();
        $client->select($db);
        return $client;
    }

    public function index(Request $request)
    {
        $db = (int) $request->query('db', 0);
        $namespaces = [];
        $serverInfo = [];
        $dbCount = 16;
        $info = [];

        try {
            $redis = $this->getRedisClient($db);

            try {
                $configDbs = $redis->config('GET', 'databases');
                if (isset($configDbs['databases'])) $dbCount = (int) $configDbs['databases'];
            } catch (\Exception $e) {
            }

            $info = $redis->info();
            $serverInfo = [
                'version' => $info['Server']['redis_version'] ?? __('redis.unknown'),
                'keys'    => $redis->dbSize(),
                'memory'  => $info['Memory']['used_memory_human'] ?? __('redis.unknown'),
                'uptime'  => $info['Server']['uptime_in_days'] ?? 0,
            ];

            $keys = $redis->keys('*');
            sort($keys);

            foreach ($keys as $key) {
                $parts = explode(':', $key);
                $current = &$namespaces;
                for ($i = 0; $i < count($parts) - 1; $i++) {
                    $part = $parts[$i];
                    if (!isset($current[$part])) $current[$part] = [];
                    $current = &$current[$part];
                }
                $current[end($parts)] = ['__key__' => $key];
            }
        } catch (CommunicationException $e) {
            $serverInfo['error'] = __('redis.connection_failed');
        }

        return Inertia::render('Dashboard', [
            'tree'       => $namespaces,
            'serverInfo' => $serverInfo,
            'currentDb'  => $db,
            'databases'  => $dbCount,
            'keyspace'   => $info['Keyspace'] ?? [],
        ]);
    }

    public function show(Request $request)
    {
        $key = $request->query('key');
        if (!$key) return response()->json(['error' => __('redis.key_not_specified')], 400);

        try {
            $redis = $this->getRedisClient((int) $request->query('db', 0));
            if (!$redis->exists($key)) return response()->json(['error' => __('redis.key_not_found')], 404);

            $type = $redis->type($key);
            $ttl = $redis->ttl($key);
            $data = null;

            switch ($type) {
                case 'string':
                    $data = $redis->get($key);
                    break;
                case 'hash':
                    $data = $redis->hGetAll($key);
                    ksort($data);
                    break;
                case 'list':
                    $data = $redis->lRange($key, 0, -1);
                    break;
                case 'set':
                    $data = $redis->sMembers($key);
                    sort($data);
                    break;
                case 'zset':
                    $data = $redis->zRange($key, 0, -1, ['WITHSCORES' => true]);
                    break;
            }
            $size = is_array($data) ? count($data) : strlen((string)$data);

            return response()->json(compact('key', 'type', 'ttl', 'size', 'data'));
        } catch (CommunicationException $e) {
            return response()->json(['error' => __('redis.connection_error')], 500);
        }
    }

    public function destroy(Request $request)
    {
        try {
            $this->getRedisClient((int) $request->input('db', 0))->del($request->input('key'));
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function rename(Request $request)
    {
        try {
            $key = $request->input('key');
            $newKey = $request->input('new_key');
            if ($key !== $newKey) $this->getRedisClient((int) $request->input('db', 0))->rename($key, $newKey);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateTtl(Request $request)
    {
        try {
            $redis = $this->getRedisClient((int) $request->input('db', 0));
            $ttl = (int) $request->input('ttl');
            $ttl === -1 ? $redis->persist($request->input('key')) : $redis->expire($request->input('key'), $ttl);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function updateValue(Request $request)
    {
        try {
            $redis = $this->getRedisClient((int) $request->input('db', 0));
            $key = $request->input('key');
            $newValue = $request->input('new_value');

            switch ($request->input('type')) {
                case 'string':
                    $redis->set($key, $newValue);
                    break;
                case 'hash':
                    $redis->hset($key, $request->input('field'), $newValue);
                    break;
                case 'list':
                    $redis->lset($key, $request->input('field'), $newValue);
                    break;
                case 'set':
                    if ($request->input('old_value') !== $newValue) {
                        $redis->srem($key, $request->input('old_value'));
                        $redis->sadd($key, $newValue);
                    }
                    break;
                case 'zset':
                    $redis->zrem($key, $request->input('old_value'));
                    $redis->zadd($key, $request->input('score'), $newValue);
                    break;
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function deleteValue(Request $request)
    {
        try {
            $redis = $this->getRedisClient((int) $request->input('db', 0));
            $key = $request->input('key');
            switch ($request->input('type')) {
                case 'hash':
                    $redis->hdel($key, $request->input('field'));
                    break;
                case 'list':
                    $marker = 'DEL_' . uniqid();
                    $redis->lset($key, $request->input('field'), $marker);
                    $redis->lrem($key, 1, $marker);
                    break;
                case 'set':
                    $redis->srem($key, $request->input('value'));
                    break;
                case 'zset':
                    $redis->zrem($key, $request->input('value'));
                    break;
            }
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function flushDb(Request $request)
    {
        try {
            $this->getRedisClient((int) $request->input('db', 0))->flushdb();
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        try {
            $keys = $request->input('keys');
            if (empty($keys) || !is_array($keys)) return response()->json(['error' => __('redis.no_keys_selected')], 400);
            $this->getRedisClient((int) $request->input('db', 0))->del($keys);
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
