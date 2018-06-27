<?php

/*
https://www.jianshu.com/p/fbdad6f77d0c

网络请求模块
爬取流程控制模块
内容分析提取模块
网络请求

我们常说爬虫其实就是一堆的http(s)请求，找到待爬取的链接，然后发送一个请求包，得到一个返回包，当然，也有HTTP长连接(keep-alive)或h5中基于stream的websocket协议，这里暂不考虑，所以核心的几个要素就是：

url
请求header、body
响应herder、内容
URL

爬虫开始运行时需要一个初始url，然后会根据爬取到的html文章，解析里面的链接，然后继续爬取，这就像一棵多叉树，从根节点开始，每走一步，就会产生新的节点。为了使爬虫能够结束，一般都会指定一个爬取深度(Depth)。

Http请求

http请求信息由请求方法(method)、请求头(headers)、请求正文(body)三部分组成。由于method一般是header中的第一行，也可以说请求头中包含请求方法，下面是chrome访问请求头的一部分：

GET / HTTP/1.1
Connection:Keep-Alive
Host:gsw.iguoxue.org
User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36
Accept-Encoding:gzip, deflate, sdch, br
本文不会解释各个字段的意思，详细的解释请移步w3c Http Header Field Definitions . 对于爬虫需要注意的是请求方法是post时，需要将请求的参数先进行urlencode后再发送，后台收到请求信息后可能会做一些校验，这可能会影响到爬取，相关的header字段如下：

Basic Auth
这是一种古老的、不安全的用户验证方式，一般会有用户授权的限制，会在headers的Autheration字段里要求加入用户名密码(明文)，如果验证失败则请求就会失败，现在这种认证方式正在被淘汰。

Referer
链接的来源，通常在访问链接时，都要带上Referer字段，服务器会进行来源验证，后台通常会用此字段作为防盗链的依据。

User-Agent
后台通常会通过此字段判断用户设备类型、系统以及浏览器的型号版本。有些编程语言包里网络请求会自定义User-Agent，可以被辨别出来，爬虫中可以设置为浏览器的ua.

Cookie
一般在用户登录或者某些操作后，服务端会在返回包中包含Cookie信息要求浏览器设置Cookie，没有Cookie会很容易被辨别出来是伪造请求；

也有本地通过JS，根据服务端返回的某个信息进行处理生成的加密信息，设置在Cookie里面；

JavaScript加密操作
在进行敏感数据传输时，一般都会通过javascript进行加密，例如qq空间就会对用户登陆密码进行RSA加密后再发送给服务器，因此，爬虫在模拟登陆时需要自己去请求公钥，然后加密。

自定义字段
因为http的headers可以自定义地段，所以第三方可能会加入了一些自定义的字段名称或者字段值，这也是需要注意的。

流程控制

所谓爬取流程，就是按照什么样的规则顺序去爬。在爬取任务不大的情况下，爬取的流程控制不会太麻烦，很多爬取框架都已经帮你做了如scrapy，只需要自己实现解析的代码。但在爬取一些大型网站时，例如全网抓取京东的评论，微博所有人的信息，关注关系等等，这种上十亿到百亿次设置千亿次的请求必须考虑效率，否则一天只有86400秒，那么一秒钟要抓100次，一天也才8640w次请求，也需要100多天才能到达十亿级别的请求量。涉及到大规模的抓取，一定要有良好的爬虫设计，一般很多开源的爬虫框架也都是有限制的，因为中间涉及到很多其他的问题，例如数据结构，重复抓取过滤的问题，当然最重要的是要把带宽利用满，所以分布式抓取很重要，这时流程控制就会很重要，分布式最重要的就是多台机器不同线程的调度和配合，通常会共享一个url队列，然后各个线程通过消息通信，如果想要抓的越多越快，那么对中间的消息系统的吞吐量要求也越高。现在也有一些开源的分布式爬取框架如scrapy-redis就是一个重写了scrapy的调度模块、队列、管道的包，redis数据库是用来在分布式中做请求队列共享，scrapyd是用来部署scrapy的,scrapyd-api用来启动获取数据。

内容分析提取

请求headers的Accept-Encoding字段表示浏览器告诉服务器自己支持的压缩算法（目前最多的是gzip），如果服务器开启了压缩，返回时会对响应体进行压缩，爬虫需要自己解压；

过去我们常需要获取的内容主要来源于网页html文档本身，也就是说，我们决定进行抓取的时候，都是html中包含的内容，但是随着这几年web技术飞速的发展，动态网页越来越多，尤其是移动端，大量的SPA应用，这些网站中大量的使用了ajax技术。我们在浏览器中看到的网页已不全是html文档说包含的，很多都是通过javascript动态生成的，一般来说，我们最终眼里看到的网页包括以下三种：

Html文档本身包含内容
这种情况是最容易解决的，一般来讲基本上是静态网页已经写死的内容，或者动态网页，采用模板渲染，浏览器获取到HTML的时候已经是包含所有的关键信息，所以直接在网页上看到的内容都可以通过特定的HTML标签得到。这种情况解析也是很简单的，一般的方法有一下几种：

CSS选择器
XPATH（这个值得学习一下）
正则表达式或普通字符串查找
JavaScript代码加载内容
一般来说有两种情况：一种情况是在请求到html文档时，网页的数据在js代码中，而并非在html标签中，之所以我们看到的网页是正常的，那是因为，其实是由于执行js代码动态添加到标签里面的，所以这个时候内容在js代码里面的，而js的执行是在浏览器端的操作，所以用程序去请求网页地址的时候，得到的response是网页代码和js的代码，所以自己在浏览器端能看到内容，解析时由于js未执行，肯定找到指定HTML标签下内容肯定为空，如百度的主页就是这种，这个时候的处理办法，一般来讲主要是要找到包含内容的js代码串，然后通过正则表达式获得相应的内容，而不是解析HTML标签。另一种情况是在和用户交互时，JavaScript可能会动态生成一些dom，如点击某个按钮弹了一个对话框等；对于这种情况，一般这些内容都是一些用户提示相关的内容，没什么价值，如果确实需要，可以分析一下js执行逻辑，但这样的情况很少。

Ajax／Fetch异步请求
这种情况是现在很常见的，尤其是在内容以分页形式显示在网页上，并且页面无刷新，或者是对网页进行某个交互操作后，得到内容。对于这种页面，分析的时候我们要跟踪所有的请求，观察数据到底是在哪一步加载进来的。然后当我们找到核心的异步请求的时候，就只需抓取这个异步请求就可以了，如果原始网页没有任何有用信息，也没必要去抓取原始网页了。

 */

//--------------------->>第一：不涉及解析html结构

/*---------    https://www.rong360.com/credit/article  信用开资讯 的今日热文 ------------*/
//1.直接抓取“Html文档本身包含内容”包含的内容
$data = file_get_contents("https://www.rong360.com/credit/article");

/*
编码：
GBK编码的转UTF-8，不转直接输出时中文乱码
$content = file_get_contents('http://www.phpcms.cn');

$html_content = mb_convert_encoding($content, 'UTF-8', 'gbk');

//file_put_contents('g1.html', $html_content);
echo $html_content;
 */

$data = file_get_contents("http://stock.hexun.com/7x24h/");

echo $data;die;
$data = mb_convert_encoding($data, 'UTF-8', 'gb2312');

file_put_contents("1.txt", $data);

/*---------   采集 http://stock.hexun.com/7x24h/ 上的”和讯7x24小时金融市场直播“ ------------*/
//2.Ajax／Fetch异步请求.    进入该网站调试模式观察得到：http://nwapi.hexun.com/liveNews/getNews?size=20&callback=ptemplate_jsonp_09038859470501757 这个网址得到的数据对应页面我们想要的资讯列表，经测试直接去掉参数化callback也可以正常得到我们想要的数据。http://nwapi.hexun.com/liveNews/getNews?size=20 直接调用接口获得数据，一般返回的结果是json,谷歌f12调试模式，点击network,查看第一个链接，查看response,如果没有收到想要内容，估计是第一个链接
//加载完之后，页面去调用接口获得数据然后加载到页面上，这个时候一般只要找到这个接口地址，利用这个接口地址就可以得到我们想要的数据

$data = file_get_contents("http://nwapi.hexun.com/liveNews/getNews?size=10");
$arr = json_decode($data, true);

foreach ($arr['list'] as $k => $v) {
    $param = [];
    $content = '';
    $key = trim($v['id']);

    $content = str_replace("(和讯财经app推送)", '', $v['content']);
    $content = trim($content);
    // $has_content = $this->cyl_news_model->get_row(['n_content' => $content, 'n_source' => '快讯']);
    // if ($has_content) {
    //     continue;
    // }

    $param['n_source'] = '快讯';

    $param['n_content'] = $content;
    $param['n_key'] = $key;
    $param['n_time'] = date("Y-m-d H:i:s", $v["timestamp"] / 1000);
    $param['n_create_time'] = date("Y-m-d H:i:s");

    var_dump($param);
    //添加到数据库等操作

}

/*---------   采集 https://www.qimai.cn/ 上的”搜索app，搜索列表第一个app的app图标，ios 下载url, android的 Bundle ID“ ------------*/
//3.爬虫抓取网页调试时遇到‘Paused in debugger‘’如何解决？  方法一：分析Ajax／Fetch异步请求，找到我们需要的接口地址; 方法2：利用phantomjs
/*

第一步：获取搜索列表第一个app的appid

搜索"人人贷"， network查看请求地址：https://api.qimai.cn/search/index?analysis=LHV3Gn0iJAItNngDfW0rUXB0IAAuCXZAf1p2TnsLdEsucgIUfg9RTy8YcEZ8bQUXdHQ8QCwJVFB9ZFAPeyV0BSx1cAlSCw1BACUARGtcMVVMRTYCFBZeB0RHCgR2QhpABFBAAFhJCFgFB0FxEghWWg4AVgFUUwYAcBMG&country=cn&search=%E4%BA%BA%E4%BA%BA%E8%B4%B7&date=2018-06-27&device=iphone&page=1&sdate=2018-06-26+18:00:00&edate=2018-06-27+18:00:00&appid=0

echo urldecode('https://api.qimai.cn/search/index?analysis=LHV3Gn0iJAItNngDfW0rUXB0IAAuCXZAf1p2TnsLdEsucgIUfg9RTy8YcEZ8bQUXdHQ8QCwJVFB9ZFAPeyV0BSx1cAlSCw1BACUARGtcMVVMRTYCFBZeB0RHCgR2QhpABFBAAFhJCFgFB0FxEghWWg4AVgFUUwYAcBMG&country=cn&search=%E4%BA%BA%E4%BA%BA%E8%B4%B7&date=2018-06-27&device=iphone&page=1&sdate=2018-06-26+18:00:00&edate=2018-06-27+18:00:00&appid=0');

得到：
https://api.qimai.cn/search/index?analysis=LHV3Gn0iJAItNngDfW0rUXB0IAAuCXZAf1p2TnsLdEsucgIUfg9RTy8YcEZ8bQUXdHQ8QCwJVFB9ZFAPeyV0BSx1cAlSCw1BACUARGtcMVVMRTYCFBZeB0RHCgR2QhpABFBAAFhJCFgFB0FxEghWWg4AVgFUUwYAcBMG&country=cn&search=人人贷&date=2018-06-27&device=iphone&page=1&sdate=2018-06-26 18:00:00&edate=2018-06-27 18:00:00&appid=0

第二步：利用第一步得到appid得到ios信息
请求这个地址得到的结果和页面上搜索得到的结果一致，说明用这个接口可以完成我们想要的结果的第一步：搜索列表的第一个app,点击这个app,进入新页面：app的ios详情页面，调试观察到请求app ios信息请求接口：https://api.qimai.cn/app/appinfo?analysis=LnVVGX4yOE4sNmhIaAtWXnkTSlURExhWQEBeV1AOdRBQBQtUAVZTBVJQDnESCA%3D%3D&appid=883561142&country=cn,
可以去掉analysis参数，直接https://api.qimai.cn/app/appinfo?appid=appid&country=cn

此时得到app的ios信息，同时得到androidId, 比如141

第三步：在同一个页面点击“发现安卓版”得到app的安卓信息接口，https://api.qimai.cn/andapp/appinfo?analysis=LGVjG3BFTlcPBlhBQRYDE0lZC1IOIxQGBAkACAVSAgdVA3JAAQ%3D%3D&appid=141 ，可以去掉analysis参数。

测试：https://api.qimai.cn/andapp/appinfo?appid=141这样就可以得到app的安卓版信息

 */

//要搜索的应用名称
$apps = ['爱投资', '人人贷', '拍怕贷', '你我贷', '宜贷网'];

//第一步：搜索,  '人人贷'为例子

$appName = '人人贷';

$url1 = "https://api.qimai.cn/search/index?country=cn&search={$appName}&date=2018-06-27&device=iphone&page=1&sdate=2018-06-26 14:00:00&edate=2018-06-27 14:00:00&appid=0";

// echo $url1;die;
$str1 = file_get_contents($url1);

$arr1 = json_decode($str1, true);
$list1 = $arr1["appList"];
$appid = $list1[0]['appInfo']['appId'];

//第二步，详情

//ios
$url2 = "https://api.qimai.cn/app/appinfo?appid={$appid}&country=cn";
$str2 = file_get_contents($url2);
$arr2 = json_decode($str2, true);

$androidId = $arr2['appInfo']['androidId'];

var_dump($arr2);

echo "<br>-----------------------------------<br>";
//android
$url21 = "https://api.qimai.cn/andapp/appinfo?appid={$androidId}";
$str21 = file_get_contents($url21);
$arr21 = json_decode($str21, true);
var_dump($arr21);

//--------------------->>第二：涉及解析html结构

/*----------------抓取凤凰财经”今日要闻“ http://finance.ifeng.com/----------------------------*/
/*
nokogiri: git地址： https://github.com/olamedia/nokogiri
nokogiri，这是一个html的dom解析类库，可以快速的解析html，获得节点下的属性，文本，子节点等
Nokogiri是一个非常迅捷的XML/HTML解析器，可以通过Xpath和CSS定位，非常方便

 */
