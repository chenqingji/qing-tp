<?php
/**
 * Created by PhpStorm.
 * User: kzangv
 * Date: 2016/6/16
 * Time: 14:24
 */

namespace Index\Model;


class CalculateFeeModel {
//  protected $postExceptArea = array("西藏","新疆");
    static public $emsArea = [
        '青海' => [
            '海东地区' => ['循化县' => 1, '化隆县' => 1],
            '海西州' => ['乌兰县' => 1,],
            '海南州' => ['同德县' => 1, '兴海县' => 1, '贵南县' => 1],
            '黄南州' => ['泽库县' => 1, '尖扎县' => 1, '河南县' => 1],
            '果洛州' => ['玛沁县' => 1, '班玛县' => 1, '甘德县' => 1, '达日县' => 1, '久治县' => 1, '玛多县' => 1],
            '玉树州' => ['玉树县' => 1, '杂多县' => 1, '称多县' => 1, '治多县' => 1, '囊谦县' => 1, '曲麻莱县' => 1]
        ],
        '四川' => [
            '阿坝州' => ['若尔盖县' => 1, '红原县' => 1, '阿坝县' => 1, '黑水县' => 1, '壤塘县' => 1, '金阳县' => 1, '布拖县' => 1],
            '凉山州' => ['雷波县' => 1],
            '甘孜州' => ['巴塘县' => 1, '白玉县' => 1, '丹巴县' => 1, '道孚县' => 1, '稻城县' => 1, '得荣县' => 1, '德格县' => 1, '九龙县' => 1, '理塘县' => 1, '炉霍县' => 1, '色达县' => 1, '石渠县' => 1, '乡城县' => 1, '新龙县' => 1, '雅江县' => 1]
        ],
        '云南' => [
            '怒江州' => ['福贡县' => 1, '贡山县' => 1]
        ],
        '西藏' => [
            '拉萨市' => ['林周县' => 1, '达孜县' => 1, '尼木县' => 1, '当雄县' => 1, '曲水县' => 1, '墨竹工卡县' => 1, '堆龙德庆县' => 1]
        ]
    ];

    const YUNDA = 1;
    const EMS = 2;
    static $express = [
        self::YUNDA => ['name'=> '韵达','fee' => 0],
        self::EMS => ['name'=> 'EMS','fee' => 16],
    ];

    // 获取订单价格
    static public function getFee($picsCount, $province = null, $city = null, $area = null, $street = null)
    {
        $orderFee = 9.9 +intval(($picsCount-1)/20)*8.9;
        $postFee = self::getPostFee($province, $city, $area, $street);
        $totalFee = $orderFee + $postFee;

        return [
            $orderFee,
            $postFee,
            $totalFee
        ];
    }

    // 获取订单价格
    static  public function getFeeMoli($picsCount, $province = null, $city = null, $area = null, $street = null)
    {
        return CalculateFeeModel::getFee($picsCount, $province = null, $city = null, $area = null, $street = null);
    }

    static function isEmsArea($province = null, $city = null, $area = null, $street = null)
    {
        $emsArea = self::$emsArea;
        $ret = false;
        foreach(['province', 'city', 'area'] as $v) {
            if(empty($ $v) && !isset($emsArea[$ $v])) break;
            $emsArea = $emsArea[$ $v];
            if(is_int($emsArea)) {
                $ret = true;
            }
        }
        if(!$ret && '浙江' == $province && '杭州市' == $city && '萧山区' == $area && false === strpos($street, '瓜沥')) {
            $ret = true;
        }
        return $ret;
    }

    static function getPostFee($province, $city, $area, $street)
    {
        $postFee = 0;
        if(self::isEmsArea($province, $city, $area, $street)) {
            $postFee = self::$express[self::EMS]['fee'];
        }
        return $postFee;
    }

    static function getPostDetail($province, $city, $area, $street)
    {
        $expressId = self::YUNDA;
        if(self::isEmsArea($province, $city, $area, $street)) {
            $expressId = self::EMS;
        }
        $data = [
            'isPostageFree' => (self::$express[$expressId]['fee'] > 0 ? 0 : 1),
            'postage' => self::$express[$expressId]['fee'],
            'remindHint' => "快点支付吧",
            'expressList' => [
                'isCurrent' => 1,
                'expressId' => $expressId,
                'expressName' => self::$express[$expressId]['name'],
                'expressPrice' => self::$express[$expressId]['fee'],
            ]
        ];
        return $data;
    }
} 