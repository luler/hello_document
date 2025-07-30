<?php

namespace app\common\service;


use app\common\exception\CommonException;
use Curl\Curl;
use think\facade\Cache;

/**
 * @link 参考文档地址： https://docs.zincsearch.com/
 */
class ZincSearchService
{
    public static function getConfig()
    {
        return config('zinc');
    }

    /**
     * 创建默认索引
     * @return void
     * @throws CommonException
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function createIndex()
    {
        $key = 'createIndex';
        if (Cache::has($key)) {
            return;
        }
        Cache::set($key, 1, 60 * 5); //5分钟检查一次即可
        $curl = new Curl();
        $curl->setHeader('content-type', 'application/json');
        $curl->setBasicAuthentication(self::getConfig()['username'], self::getConfig()['password']);
        $data = [];
        $data['properties'] = [
            'uid' => [
                "type" => "numeric",
                "index" => true,
                "store" => false,
                "sortable" => true,
                "aggregatable" => true,
                "highlightable" => false
            ],
            'name' => [
                "type" => "text",
                "index" => true,
                "store" => true,
                "sortable" => false,
                "aggregatable" => false,
                "highlightable" => true
            ],
            'text' => [
                "type" => "text",
                "index" => true,
                "store" => true,
                "sortable" => false,
                "aggregatable" => false,
                "highlightable" => true
            ],
        ];
        $curl->put(self::getConfig()['host'] . '/api/' . self::getConfig()['index'] . '/_mapping', json_encode($data, 256), true);
        $curl->close();
    }

    /**
     * 推送数据到搜索引擎
     * @param $records
     * @return void
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function createDocumentsBulkV2($records)
    {
        self::createIndex();
        $curl = new Curl();
        $curl->setHeader('content-type', 'application/json');
        $curl->setBasicAuthentication(self::getConfig()['username'], self::getConfig()['password']);
        $data = [];
        $data['index'] = self::getConfig()['index'];
        $data['records'] = array_map(function ($value) {
            return [
                '_id' => $value['_id'],
                'uid' => $value['uid'],
                'name' => $value['name'],
                'text' => $value['text'],
            ];
        }, $records);
        $curl->post(self::getConfig()['host'] . '/api/_bulkv2', json_encode($data, 256));
        $curl->close();
        $res = json_decode($curl->response, true) ?: [];
        if (!isset($res['record_count']) || $res['record_count'] < 1) {
            throw new CommonException('推送zincsearch失败，原因：' . $curl->response);
        }
    }

    /**
     * 删除索引文档
     * @param $doc_id
     * @return void
     * @throws CommonException
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function delDocument($doc_id)
    {
        $curl = new Curl();
//        $curl->setHeader('content-type', 'application/json');
        $curl->setBasicAuthentication(self::getConfig()['username'], self::getConfig()['password']);
        $curl->delete(self::getConfig()['host'] . '/api/' . self::getConfig()['index'] . '/_doc/' . $doc_id);
        $curl->close();
    }

    /**
     * 全文搜索内容
     * @param $keyword
     * @param $uid
     * @return array|mixed
     * @author 我只想看看蓝天 <1207032539@qq.com>
     */
    public static function searchDocument($keyword, $uid, $page = 1, $page_rows = 10)
    {
        $curl = new Curl();
        $curl->setHeader('content-type', 'application/json');
        $curl->setBasicAuthentication(self::getConfig()['username'], self::getConfig()['password']);

        $keys = explode('&', $keyword);
        $keyword_part = [
            'bool' => [
                'must' => [],
            ],
        ];
        foreach ($keys as $key) {
            if (strpos($key, '*') !== false || strpos($key, '?') !== false) {
                $key = strtolower($key); //这个很坑，必须转小写才能匹配到英文字符
                $keyword_part['bool']['must'][] = [
                    'bool' => [
                        'should' => [
                            ['wildcard' => ['name' => $key,]],
                            ['wildcard' => ['text' => $key,]],
                        ],
                    ],
                ];
            } else {
                $keyword_part['bool']['must'][] = [
                    'bool' => [
                        'should' => [
                            ['match_phrase' => ['name' => $key,]],
                            ['match_phrase' => ['text' => $key,]],
                        ],
                    ],
                ];
            }
        }
        $data = [];
        if (!empty($uid)) {
            $keyword_part['bool']['must'][] = ['term' => ['uid' => (int)$uid,]];
        }

        $data['query'] = $keyword_part;
        $data['from'] = ($page - 1) * $page_rows;
        $data['max_results'] = $page_rows;
        $data['_source'] = ['_id'];
        $data['highlight'] = [ //启用高亮返回
            'fields' => [
                'name' => new \stdClass(),
                'text' => new \stdClass(),
            ]
        ];
        $curl->post(self::getConfig()['host'] . '/es/' . self::getConfig()['index'] . '/_search', json_encode($data, 256));
        $curl->close();
        $res = json_decode($curl->response, true) ?: [];
        return $res;
    }
}