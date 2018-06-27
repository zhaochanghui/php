<?php

include 'Nokogiri.php';

$html = request("http://finance.ifeng.com/");

$saw = new Nokogiri();
$saw->loadHtmlNoCharset($html);
$data1 = $saw->get('.box_02 ul:nth-child(1) li a')->toArray();
$data2 = $saw->get('.box_02 ul:nth-child(2) li a')->toArray();
$data4 = $saw->get('.box_02 ul:nth-child(4) li a')->toArray();

save($data1);

function save($arr)
{

    foreach ($arr as $k => $v) {
        $param = [];
        $url = $v["href"];
        $title1 = $v["#text"][0];

        $title = '';

        $res = getDetail($url, $title1);

        if ($res['n_title'] == null) {
            continue;
        } else {
            $title = $res['n_title'];
        }

        if (!$res) {
            continue;
        }

        // $has = $this->cyl_news_model->get_row(['n_title' => $title, 'n_source' => '推荐']);
        // if ($has) {
        //     continue;
        // }

        $param['n_source'] = '推荐';
        $param['n_title'] = $title;
        $param['n_content'] = $res['n_content'];
        $param['n_img'] = $res['n_img'];
        $param['n_time'] = $res['n_time'];

        if (strpos($param['n_time'], '月')) {

            $param['n_time'] = str_replace('年', '-', $param['n_time']);
            $param['n_time'] = str_replace('月', '-', $param['n_time']);
            $param['n_time'] = str_replace('日', '', $param['n_time']);
        }

        $param['n_create_time'] = date("Y-m-d H:i:s");

        // $this->cyl_news_model->add($param);

    }

}

/**
 * 资讯详情
 * @param  [type] $url 文章地址
 * @return [type]      [description]
 */
function getDetail($url, $list_title)
{
    $html = request($url);

    $saw = new Nokogiri();
    $saw->loadHtmlNoCharset($html);

    $xml = $saw->get('#main_content')->toXml();
    $pos = strpos($html, 'id="main_content"');
    if (!$pos) {
        return false;
    }

    //详情标题
    $titleArr = $saw->get("#artical_topic")->toArray();
    $title = '';
    if (isset($titleArr[0]['#text'][0])) {
        $title = $titleArr[0]['#text'][0];
    } else {
        return false;
    }

    if ($title != $list_title) {
        // var_dump($list_title);
        // echo "标题变了\n";
    }

    $timeArr = $saw->get(".ss01")->toArray();

    $img = $saw->get(".detailPic")->toArray();

    $res['n_time'] = $timeArr[0]["#text"][0];
    $regex4 = "/<span class=\"ifengLogo\">.*?<\/span>/ism";

    preg_match_all($regex4, $xml, $matches);

    $xml = str_replace($matches[0][0], '', $xml);

    $res['n_img'] = '';
    if (isset($img[0]['img'][0]['src'])) {
        $res['n_img'] = $img[0]['img'][0]['src'];
    }

    $res['n_content'] = $xml;
    $res['n_title'] = $title;
    return $res;
}

//CURL获得网页内容
function request($url)
{

    //1. 初始化
    $ch = curl_init();

    //2. 设置选项
    curl_setopt($ch, CURLOPT_URL, $url); // 设置要抓取的页面地址
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 抓取结果直接返回（如果为0，则直接输出内容到页面）
    curl_setopt($ch, CURLOPT_HEADER, 0); // 不需要页面的HTTP头

    //3. 执行并获取HTML文档内容，可用echo输出内容
    $html = curl_exec($ch);

    //4. 释放curl句柄
    curl_close($ch);

    return $html;
}
