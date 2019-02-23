<?php
namespace app\index\controller;
ini_set('max_execution_time', '1800');
ini_set('post_max_size', '0');
ini_set('memory_limit', '-1');

use app\common\controller\Base;
use app\common\model\News;
use think\Db;
use app\index\crawl\Crawl as CrawlHelper;
use Symfony\Component\DomCrawler\Crawler as DOMCrawler;

class Crawl extends Base
{
    private $crawler;
    private $sinaJson;

    protected function initialize()
    {
        $this->crawler = new CrawlHelper();

        $this->sinaJson = json_decode(file_get_contents('./json/sina.json'), true);
        $this->eastdayJson = json_decode(file_get_contents('./json/eastday.json'), true);
        $this->testJson = json_decode(file_get_contents('./json/test.json'), true);

        // set default last crawl date
        $this->defaultLastCrawlDate = time() - (24 * 60 * 60);
    }

    private function insertIntoDb($allNews)
    {
        foreach($allNews as $k=>$news){
            $allNews[$k] = $this->toTableFormat($news);
        }

        //dump($allNews); exit;

        Db::startTrans();
        try{
            Db::name('news')->insertAll($allNews);
            Db::commit();
            echo "Insert successfully in db...\n";
        }catch(\Exception $e){
            Db::rollback();
            echo "Insert failed";
        }
    }

    private function toTableFormat($news)
    {
        return [
            'news_title'=>trim($news['title']),
            'news_columnid'=>$news['columnid'],
            'news_columnviceid'=>$news['columnviceid'],
            'news_auto'=>1,
            'news_source'=>$news['source'],
            'news_content'=>trim($news['content']),
            'news_scontent'=>trim($news['scontent']),
            'news_img'=>$news['image'],
            'news_pic_type'=>1,
            'news_time'=>$news['pdate'],
            'news_back'=>0,
            'news_open'=>1,
            'comment_status'=>1
        ];
    }

    public function crawlAll()
    {
        foreach($this->eastdayJson['categories'] as $index=>$category){
            if($category['listType'] == 'JSON'){
                //$this->testCrawl();
            }else if($category['listType'] == "JSONHTML" || $category['listType'] == 'HTMLJSON'){
                $this->crawlHtmlJson($index);
            }else{
                //$this->testCrawlHtml();
            }
        }

        // write back to corresponding JSON file
        $this->toJsonFile($this->eastdayJson, './json/eastday.json');
    }

    public function test()
    {
        $categories = $this->testJson['categories'];
        $category = $categories[0];

        $url = "http://mini.eastday.com/a/190222092251922.html";
        $html = $this->crawler->getHtml($url);
        $content = $this->getWithDOMCrawler(".J-contain_detail_cnt",$html);
        $content = is_null($content) ? '' : $content;
        echo $content;
        //return $content;
    }

    public function testCrawlContent()
    {
        $url = "http://mini.eastday.com/a/190222092251922.html";
        $html = $this->crawler->getHtml($url);
        $domCrawler = new DOMCrawler($html);

        $content = $domCrawler->filter('.J-contain_detail_cnt')
            ->first()
            ->html();
        dump($content);
    }

    public function testJsonIO()
    {
        $cats = $this->testJson['categories'];
        foreach($cats as $k=>$cat){
            if(isset($cat['updated_at'])){
                dump($cat['updated_at']);
            }
            if(isset($cat['pattern']))
                dump($cat['pattern']);
            $this->updateTimestamp($this->testJson, $k);
        }
        $this->toJsonFile($this->testJson,'./json/test.json');
    }

    public function crawlHtmlJson($catIndex)
    {
        $crawler = $this->crawler;
        $categories = $this->eastdayJson['categories'];
        $category = $categories[$catIndex];

        $links = [];
        $numNews = 0;
        $end = false;
        $params = $category['params'];

        $imgRule = $this->getImgRule($category);

        $modifyRule = $category['modifyKeys'];

        while(!$end && $numNews <= $crawler->getLimit()){
            $tempLinks = [];
            if($numNews == 0 || !isset($category['modifyUri']))
                $url = $this->createUrl($category['uri'], $params, !isset($category['irregParams']) ? null : $category['irregParams']);
            else{
                $url = $this->createUrl($category['modifyUri'], $params);
            }

            $html = $crawler->getHtml($url);
            if(isset($category['trimVar'])){
                foreach($category['trimVar'] as $trimRule){
                    $html = preg_replace($trimRule['pattern'], $trimRule['replacement'], $html);
                }
            }
            $json = json_decode($html, true);
            $list = $this->getList($category, $json);
            if(!is_array($list)){
                break;
            }

            foreach($list as $k=>$item){
                if($numNews > $crawler->getLimit())
                    break;
                $nonkeys = ['notTimestamp', 'pdateRepPattern', 'linkPrepend', 'image'];
                $itemData['image'] = $this->getImg($item, $imgRule);
                $keys = array_diff(array_keys($category['setKeys']), $nonkeys);
                foreach($keys as $key){
                    $itemData[$key] = $item[$category['setKeys'][$key]];
                    if($key == 'pdate'){
                        $itemData[$key] = $this->translateDate($itemData[$key], !isset($category['setKeys']['pdateRepPattern']) ? null : $category['setKeys']['pdateRepPattern']);
                    }
                    if($key == 'link' && isset($category['setKeys']['linkPrepend'])){
                        $itemData[$key] = $category['setKeys']['linkPrepend'] . $itemData[$key];
                    }
                }
                $itemData['columnid'] = $category['columnid'];
                $itemData['columnviceid'] = $category['columnviceid'];
                array_push($tempLinks, $itemData);
                $numNews++;
            }

            foreach($tempLinks as $k=>$link){
                $pageHtml = $crawler->getHtml($link['link']);
                $nonKeys = ['pdateRepPattern', 'useDOMCrawler'];
                $keys = array_diff(array_keys($category['itemPage']), $nonKeys);
                foreach($keys as $key){
                    if(!isset($category['itemPage']['useDOMCrawler'])){
                        $link[$key] = $crawler->getProp($category['itemPage'][$key], $pageHtml);
                    }else{
                        $link[$key] = $this->getWithDOMCrawler($category['itemPage'][$key], $pageHtml);
                    }

                    if($key == 'pdate'){
                        $link[$key] = $this->translateDate($link[$key], !isset($category['itemPage']['pdateRepPattern']) ? null : $category['itemPage']['pdateRepPattern']);
                    }
                }
                if(!is_null($link['content'])){
                    $link['scontent'] = (!isset($link['scontent']) || empty($link['scontent'])) ? $this->generateScontent($link['content']) : $link['scontent'];
                }

                $pDate = isset($category['setKeys']['notTimestamp']) ? strtotime($link['pdate']) : $link['pdate'];
                $link['pdate'] = $pDate;
                $tempLinks[$k] = $link;
            }
            //dump($tempLinks); exit;

            $filterResult = $this->filterLinks($tempLinks, $category);
            //$end = !$filterResult['status'];

            $links = array_merge($links, $filterResult['result']);

            if(isset($modifyRule)){
                if(isset($category['modifyParams'])){
                    $lastItemKey = count($list) - 1;
                    if($lastItemKey < 0){
                        break;
                    }
                    $nextStartKey = $list[$lastItemKey]['rowkey'];
                    $params = $category['modifyParams'];
                    $params['startkey'] = $nextStartKey;
                }
                $params = $this->modifyParams($params, $modifyRule);

            }else
                $end = true;

            //$numNews += count($filterResult['result']);
        }

        // done, got all links
        // update timestamp
        //dump($links);
        $this->insertIntoDb($links);
        $this->updateTimestamp($this->eastdayJson, $catIndex);
    }

    public function testSinaJson()
    {
        dump($this->sinaJson);
    }

    public function testCrawl()
    {
        $crawler = $this->crawler;
        $categories = $this->sinaJson['categories'];
        $category = $categories[3];

        $links = [];
        $numNews = 0;
        $end = false;
        $params = $category['params'];

        $imgRule = $category['setKeys']['image'];
        foreach($imgRule as $k=>$v){
            $imgRule[$k] = explode(',', $v);
        }

        $modifyRule = $category['modifyKeys'];

        while(!$end && $numNews <= $crawler->getLimit()){
            $url = $this->createUrl($category['uri'], $params);
            $json = $crawler->getHtml($url);
            if(isset($category['trimVar'])){
                $json = preg_replace("/^var.*?=.*?{/", "{", $json);
                $json = preg_replace("/};$/", "}", $json);
            }
            $json = json_decode($json, true);
            $list = $this->getList($category, $json);
            //dump(is_array($list));dump($list); exit;
            if(!is_array($list)){
                break;
            }
            foreach($list as $k=>$item){
                $pDate = isset($category['setKeys']['notTimestamp']) ? strtotime($item[$category['setKeys']['pdate']]) : $item[$category['setKeys']['pdate']];
                if(!$this->checkLastUpdated($category, $pDate) || $numNews > $crawler->getLimit()){
                    $end = true;
                    break;
                }
                $img = $this->getImg($item, $imgRule);

                if(is_null($img) || (isset($item['urls']) && count(json_decode($item['urls'])) > 1)){
                    continue;
                }
                $itemData = [
                    'title'=>$item[$category['setKeys']['title']],
                    'scontent'=>$item[$category['setKeys']['scontent']],
                    'pdate'=>$pDate,
                    'link'=>$item[$category['setKeys']['link']],
                    'image'=>$img,
                    'content'=>'',
                    'source'=>$item[$category['setKeys']['source']],
                    'columnid'=>$category['columnid'],
                    'columnviceid'=>$category['columnviceid']
                ];
                array_push($links, $itemData);
                $numNews++;
            }

            if(!is_null($modifyRule))
                $params = $this->modifyParams($params, $modifyRule);
            else
                $end = true;
        }

        foreach($links as $k=>$link){
            $html = $crawler->getHtml($link['link']);
            preg_match($category['content'], $html, $content);
            if(!empty($content)){
                $link['content'] = $content[1];
                $link['scontent'] = empty($link['scontent']) ? $this->generateScontent($content[1]) : $link['scontent'];
            }
            $links[$k] = $link;
        }
        dump($links);

        $this->sinaJson['categories'][3]['lastUpdated'] = time();
        $json_str = json_encode($this->sinaJson);
        file_put_contents('./json/sina.json', $json_str);
    }

    public function testCrawlJsonHtml()
    {
        $crawler = $this->crawler;
        $categories = $this->sinaJson['categories'];
        $category = $categories[17];

        $links = [];
        $numNews = 0;
        $end = false;
        $params = $category['params'];

        $modifyRule = $category['modifyKeys'];

        while(!$end && $numNews <= $crawler->getLimit()){
            $url = $this->createUrl($category['uri'], $params);
            $json = $crawler->getHtml($url);
            if(isset($category['trimVar'])){
                $json = preg_replace("/^var.*?=.*?{/", "{", $json);
                $json = preg_replace("/};$/", "}", $json);
            }
            $json = json_decode($json, true);
            $listHtml = $this->getList($category, $json);
            $list = preg_split($category['item'], $listHtml);
            array_shift($list);
            //dump(is_array($list));dump($list); exit;
            if(!is_array($list)){
                break;
            }
            foreach($list as $k=>$item){
                $pDate = $crawler->getProp($category['setKeys']['pdate'], $item);
                $pDate = isset($category['setKeys']['notTimestamp']) ? strtotime($pDate) : $pDate;
                if(!$this->checkLastUpdated($category, $pDate) || $numNews > $crawler->getLimit()){
                    $end = true;
                    break;
                }
                $img = $crawler->getProp($category['setKeys']['image'], $item);

                if(is_null($img)){
                    continue;
                }
                $nonkeys = ['notTimestamp', 'pdate'];
                $keys = array_diff(array_keys($category['setKeys']), $nonkeys);
                foreach($keys as $key){
                    $itemData[$key] = $crawler->getProp($category['setKeys'][$key], $item);
                }
                $itemData['pdate'] = $pDate;
                $itemData['columnid'] = $category['columnid'];
                $itemData['columnviceid'] = $category['columnviceid'];
                array_push($links, $itemData);
                $numNews++;
            }

            if(!is_null($modifyRule))
                $params = $this->modifyParams($params, $modifyRule);
            else
                $end = true;
        }

        foreach($links as $k=>$link){
            $html = $crawler->getHtml($link['link']);
            foreach($category['itemPage'] as $pageKey=>$pattern){
                $link[$pageKey] = $crawler->getProp($pattern, $html);
            }
            if(!is_null($link['content'])){
                $link['scontent'] = empty($link['scontent']) ? $this->generateScontent($link['content']) : $link['scontent'];
            }
            $links[$k] = $link;
        }
        dump($links);
    }

    public function testCrawlHtml()
    {
        $crawler = $this->crawler;
        $categories = $this->sinaJson['categories'];
        $category = $categories[1];//24;23;

        $links = [];
        $numNews = 0;
        $end = false;
        $params = $category['params'];

        $modifyRule = $category['modifyKeys'];

        while(!$end && $numNews <= $crawler->getLimit()){
            $tempLinks = [];
            $url = $this->createUrl($category['uri'], $params, !isset($category['irregParams']) ? null : $category['irregParams']);
            $html = $crawler->getHtml($url);
            $listArea = $crawler->getProp($category['listArea'], $html);
            if(is_null($listArea))
                break;
            $list = preg_split($category['item'], $listArea);
            array_shift($list);
            //dump(is_array($list));dump($list); exit;
            if(!is_array($list)){
                break;
            }
            foreach($list as $k=>$item){
                if($numNews > $crawler->getLimit())
                    break;
                $nonkeys = ['notTimestamp', 'pdateRepPattern'];
                $keys = array_diff(array_keys($category['setKeys']), $nonkeys);
                foreach($keys as $key){
                    $itemData[$key] = $crawler->getProp($category['setKeys'][$key], $item);
                    if($key == 'pdate'){
                        $itemData[$key] = $this->translateDate($itemData[$key], !isset($category['setKeys']['pdateRepPattern']) ? null : $category['setKeys']['pdateRepPattern']);
                    }
                }
                $itemData['columnid'] = $category['columnid'];
                $itemData['columnviceid'] = $category['columnviceid'];
                array_push($tempLinks, $itemData);
                $numNews++;
                /*
                $pDate = $crawler->getProp($category['setKeys']['pdate'], $item);
                $pDate = isset($category['setKeys']['notTimestamp']) ? strtotime($pDate) : $pDate;
                if(!$this->checkLastUpdated($pDate) || $numNews > $crawler->getLimit()){
                    $end = true;
                    break;
                }
                $img = $crawler->getProp($category['setKeys']['image'], $item);

                if(is_null($img)){
                    continue;
                }

                array_push($links, $itemData);
                $numNews++;*/
            }

            foreach($tempLinks as $k=>$link){
                $pageHtml = $crawler->getHtml($link['link']);
                $nonKeys = ['pdateRepPattern'];
                $keys = array_diff(array_keys($category['itemPage']), $nonKeys);
                foreach($keys as $key){
                    $link[$key] = $crawler->getProp($category['itemPage'][$key], $pageHtml);
                    if($key == 'pdate'){
                        $link[$key] = $this->translateDate($link[$key], !isset($category['itemPage']['pdateRepPattern']) ? null : $category['itemPage']['pdateRepPattern']);
                    }
                }
                if(!is_null($link['content'])){
                    $link['scontent'] = empty($link['scontent']) ? $this->generateScontent($link['content']) : $link['scontent'];
                }

                $pDate = isset($category['setKeys']['notTimestamp']) ? strtotime($link['pdate']) : $link['pdate'];
                $link['pdate'] = $pDate;
                $tempLinks[$k] = $link;
            }
            //dump($tempLinks); exit;

            $filterResult = $this->filterLinks($tempLinks, $category);
            //$end = !$filterResult['status'];

            $links = array_merge($links, $filterResult['result']);

            if(!is_null($modifyRule))
                $params = $this->modifyParams($params, $modifyRule);
            else
                $end = true;

            //$numNews += count($filterResult['result']);
        }

        dump($links);
    }

    public function filterLinks($links, $category)
    {
        $result = [];
        $status = true;
        foreach($links as $link){
            if(is_null($link['pdate']) || !$this->checkLastUpdated($category, $link['pdate'])){
                $status = false;
                continue;//break;
            }
            if(!is_null($link['image']) && !is_null($link['content'])){
                array_push($result, $link);
            }
        }
        return [
            'result'=>$result,
            'status'=>$status
        ];
    }

    private function toJsonFile($json, $filename)
    {
        $json_str = json_encode($json, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        file_put_contents($filename, $json_str);
    }

    private function updateTimestamp(&$json, $catIndex)
    {
        $json['categories'][$catIndex]['updated_at'] = time();
    }

    private function checkLastUpdated($category, $timestamp)
    {
        if(isset($category['updated_at'])){
            $lastCrawlDate = $category['updated_at'];
        }else{
            $lastCrawlDate = $this->defaultLastCrawlDate;
        }

        return $lastCrawlDate < $timestamp;
    }

    public function translateDate($pdate, $pattern)
    {
        if(is_null($pattern)){
            return $pdate;
        }
        $res = $pdate;
        foreach($pattern as $before=>$after){
            $delim = mb_convert_encoding($before, "UTF-8");
            $res = str_replace($delim, $after, $res);
        }
        return strtotime($res);
    }

    private function getWithDOMCrawler($selector, $html)
    {
        $domCrawler = new DOMCrawler($html);
        try{
            $htmlStr = $domCrawler
                ->filter("$selector")
                ->first()
                ->html();
        }catch(\Exception $e){
            $htmlStr = null;
        }

        return $htmlStr;
    }

    public function generateScontent($content)
    {
        $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
        $scontent = mb_substr($scontent,0,50,'utf8')."...";
        return $scontent;
    }

    public function modifyParams($params, $modifyRule)
    {
        foreach($modifyRule as $k=>$rule){
            if($rule[0] == 0){
                $params[$k] = $rule[1];
            }else if($rule[0] == 1){
                $params[$k] += $rule[1];
            }
        }
        return $params;
    }

    public function getImg($item, $imgRule)
    {
        foreach($imgRule as $i=>$rule){
            $img = $item;
            foreach($rule as $k=>$v){
                if(!isset($img[$v]))
                    break;
                $img = $img[$v];
            }
            if(isset($img) && !empty($img))
                return $img;
        }
        return null;
    }

    public function getImgRule($category)
    {
        $imgRule = $category['setKeys']['image'];
        foreach($imgRule as $k=>$v){
            $imgRule[$k] = explode(',', $v);
        }
        return $imgRule;
    }

    public function getList($category, $json)
    {
        $listKey = explode(',',$category['responseListKey']);
        $list = $json;
        foreach($listKey as $k=>$v){
            $list = $list[$v];
        }
        return $list;
    }

    public function createUrl($uri, $params, $irregParams=null)
    {
        if(is_null($irregParams))
            return $uri . http_build_query($params);
        $url = $uri;
        foreach($params as $k=>$v){
            $url = $url . $k . $v;
        }
        return $url;
    }
}
