<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/1/26
 * Time: 17:08
 */

namespace app\index\crawl;
ini_set('max_execution_time', '1800');
ini_set('post_max_size', '0');
ini_set('memory_limit', '-1');

use app\index\model\News as NewsModel;
use Symfony\Component\DomCrawler\Crawler as DOMCrawler;

class Crawl
{
    private $limit;
    private $domCrawler;
    private $newsModel;
    private $jsons;

    public function __construct($limit=100)
    {
        $this->limit = $limit;
        $this->domCrawler = new DOMCrawler();
        $this->newsModel = new NewsModel;

        $this->jsons = json_decode(file_get_contents('./json/files.json'), true);
        foreach($this->jsons as $jsonName=>$json){
            $json['json'] = json_decode(file_get_contents($json['path']), true);
            $this->jsons[$jsonName] = $json;
        }
    }

    // Crawl methods
    public function crawlAll($jsonName=null)
    {
        if(isset($jsonName)
            && !$this->doesntExist($jsonName)
            && isset($this->jsons[$jsonName])
            && !$this->doesntExist($this->jsons[$jsonName]))
        {
            try{
                $json = $this->jsons[$jsonName];
                foreach($json['json']['categories'] as $index=>$category){
                    $this->crawl($jsonName, $index);
                }

                // write back to corresponding JSON file
                $this->toJsonFile($json['json'], $json['path']);
                return 'Crawl successful';
            }catch(\Exception $e){
                return $e->getTraceAsString();
            }

        }
        return 'JSON file doesn\'t exist';
    }

    public function crawl($jsonName, $catIndex)
    {
        $categories = $this->jsons[$jsonName]['json']['categories'];
        $category = $categories[$catIndex];

        $links = [];
        $numNews = 0;
        $end = false;
        $params = $category['params'];

        if($this->isJson($category)){
            $imgRule = $this->getImgRule($category);
            $category['imgRule'] = $imgRule;
        }
        $modifyRule = $category['modifyKeys'];
        $infoKeys = $this->getKeys($category);
        $listKeys = $infoKeys['listKeys'];
        $itemKeys = $infoKeys['itemKeys'];

        while(!$end && $numNews <= $this->getLimit()){

            if($numNews == 0 || !isset($category['modifyUri']))
                $url = $this->createUrl($category['uri'], $params, !isset($category['irregParams']) ? null : $category['irregParams']);
            else{
                $url = $this->createUrl($category['modifyUri'], $params);
            }

            $html = $this->getHtml($url);
            if(isset($category['trimVar'])){
                foreach($category['trimVar'] as $trimRule){
                    $html = preg_replace($trimRule['pattern'], $trimRule['replacement'], $html);
                }
            }

            $dataWithList = $html;
            if($this->isJson($category) || $this->isJsonHtml($category)){
                $dataWithList = json_decode($html, true);
            }
            $list = $this->getList($category, $dataWithList);
            if(!is_array($list)) break;

            //dump($list); exit;

            $listInfo = $this->getInfoFromList($category, $list, $listKeys, $numNews);
            $tempLinks = $listInfo['tempLinks'];
            $numNews = $listInfo['numNews'];
            $end = $listInfo['end'];

            $tempLinks = $this->getInfoFromItem($category, $tempLinks, $itemKeys);

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
        $this->newsModel->insertIntoDb($links);
        unset($category['imgRule']);
        $this->updateTimestamp($this->jsons[$jsonName]['json'], $catIndex);
    }

    public function test()
    {
        $this->crawl('sina', 26);
    }

    // Getters/Setters
    public function setLimit($limit)
    {
        $this->limit = $limit;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    // Helper methods
    private function filterLinks($links, $category)
    {
        $result = [];
        $status = true;
        foreach($links as $link){
            if(!isset($link['pdate'])
                || $this->doesntExist($link['pdate'])
                || !$this->checkLastUpdated($category, $link['pdate']))
            {
                $status = false;
                continue;//break;
            }
            if(isset($link['image'])
                && !$this->doesntExist($link['image'])
                && isset($link['content'])
                && !$this->doesntExist($link['content']))
            {
                array_push($result, $link);
            }
        }
        return [
            'result'=>$result,
            'status'=>$status
        ];
    }

    private function getInfoFromList($category, $list, $keys, $currNumNews)
    {
        $tempLinks = [];
        $numNews = $currNumNews;
        foreach($list as $k=>$item){
            if($numNews > $this->getLimit())
                break;

            foreach($keys as $key){
                if(($this->isHtml($category) || $this->isJsonHtml($category))
                    && $category['setKeys'][$key]['useDOMCrawler'])
                {
                    $itemData[$key] = $this->getValWithDOMCrawler($category['setKeys'][$key], $item);
                }else if($this->isHtml($category) || $this->isJsonHtml($category)){
                    $itemData[$key] = $this->getProp($category['setKeys'][$key]['pattern'], $item);
                }else{
                    $itemData[$key] = $item[$category['setKeys'][$key]];
                }

                if($key == 'pdate'){
                    if(!isset($category['setKeys']['pdateRepPattern'])
                        || $this->doesntExist($category['setKeys']['pdateRepPattern']))
                    {
                        $pattern = null;
                    }else{
                        $pattern = $category['setKeys']['pdateRepPattern'];
                    }
                    $itemData[$key] = $this->translateDate($itemData[$key], $pattern);
                }
                if($key == 'link'
                    && isset($category['setKeys']['linkPrepend'])
                    && !$this->doesntExist($category['setKeys']['linkPrepend']))
                {
                    $itemData[$key] = $category['setKeys']['linkPrepend'] . $itemData[$key];
                }
            }
            if($this->isJson($category)){
                $itemData['image'] = $this->getImg($item, $category['imgRule']);
            }
            $itemData['columnid'] = $category['columnid'];
            $itemData['columnviceid'] = $category['columnviceid'];
            array_push($tempLinks, $itemData);
            $numNews++;
        }

        return [
            'tempLinks'=>$tempLinks,
            'numNews'=>$numNews,
            'end'=>false
        ];
    }

    private function getInfoFromItem($category, $tempLinks, $keys)
    {
        $links = $tempLinks;
        foreach($links as $k=>$link){
            $pageHtml = $this->getHtml($link['link']);

            foreach($keys as $key){
                if(!$category['itemPage'][$key]['useDOMCrawler']){
                    $link[$key] = $this->getProp($category['itemPage'][$key]['pattern'], $pageHtml);
                }else{
                    $link[$key] = $this->getValWithDOMCrawler($category['itemPage'][$key], $pageHtml);
                }

                if($key == 'pdate'){
                    if(!isset($category['itemPage']['pdateRepPattern'])
                        || $this->doesntExist($category['itemPage']['pdateRepPattern']))
                    {
                        $pattern = null;
                    }else{
                        $pattern = $category['itemPage']['pdateRepPattern'];
                    }

                    $link[$key] = $this->translateDate($link[$key], $pattern);
                }
            }
            if(isset($link['content']) && !$this->doesntExist($link['content'])){
                if(!isset($link['scontent']) || $this->doesntExist($link['scontent'])){
                    $link['scontent'] = $this->generateScontent($link['content']);
                }
            }

            if(isset($category['setKeys']['notTimestamp'])
                && !$this->doesntExist($category['setKeys']['notTimestamp']))
            {
                $pDate = strtotime($link['pdate']);
            }else{
                $pDate = $link['pdate'];
            }

            $link['pdate'] = $pDate;
            $links[$k] = $link;
        }

        return $links;
    }

    private function getKeys($category)
    {
        $nonListKeys = ['notTimestamp', 'pdateRepPattern', 'linkPrepend'];
        if($this->isJson($category)){
            array_push($nonListKeys, 'image');
        }
        $nonItemKeys = ['notTimestamp', 'pdateRepPattern', 'linkPrepend'];

        $listKeys = array_diff(array_keys($category['setKeys']), $nonListKeys);
        $itemKeys = array_diff(array_keys($category['itemPage']), $nonItemKeys);
        return [
            'listKeys'=>$listKeys,
            'itemKeys'=>$itemKeys
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
        if(isset($category['updated_at']) && !$this->doesntExist($category['updated_at'])){
            $lastCrawlDate = $category['updated_at'];
        }else{
            $lastCrawlDate = $this->newsModel->getMostRecentTime($category['columnviceid']);
        }

        return $lastCrawlDate < $timestamp;
    }

    private function translateDate($pdate, $pattern)
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

    private function getValWithDOMCrawler($rule, $html)
    {
        try{
            if($rule['useDOMCrawler']){
                $this->addHtmlToCrawler($html);
                $selector = $rule['selector'];
                $crawler = $this->domCrawler->filter("$selector")->first();
                if(isset($rule['attr'])){
                    $attr = $rule['attr'];
                    return $crawler->attr("$attr");
                }
                return $crawler->html();
            }
        }catch(\Exception $e){}

        return null;
    }

    private function getProp($pattern, $html)
    {
        preg_match($pattern, $html, $res);
        if(empty($res))
            return null;
        return trim($res[1]);
    }

    private function getWithDOMCrawler($selector, $html)
    {
        $this->addHtmlToCrawler($html);
        try{
            $htmlStr = $this->domCrawler
                ->filter("$selector")
                ->first()
                ->html();
        }catch(\Exception $e){
            $htmlStr = null;
        }

        return $htmlStr;
    }

    private function generateScontent($content)
    {
        $scontent = preg_replace("/(\s|\&nbsp\;|　|\xc2\xa0)/", "", strip_tags($content));
        $scontent = mb_substr($scontent,0,50,'utf8')."...";
        return $scontent;
    }

    private function modifyParams($params, $modifyRule)
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

    private function getImg($item, $imgRule)
    {
        foreach($imgRule as $i=>$rule){
            $img = $item;
            foreach($rule as $k=>$v){
                if(!isset($img[$v]) || $this->doesntExist($img[$v]))
                    break;
                $img = $img[$v];
            }
            if(isset($img) && !$this->doesntExist($img))
                return $img;
        }
        return null;
    }

    private function getImgRule($category)
    {
        $imgRule = $category['setKeys']['image'];
        foreach($imgRule as $k=>$v){
            $imgRule[$k] = explode(',', $v);
        }
        return $imgRule;
    }

    private function getList($category, $dataWithList)
    {
        if($this->isJson($category))
        {
            $listKey = explode(',',$category['responseListKey']);
            $list = $dataWithList;
            foreach($listKey as $k=>$v){
                $list = $list[$v];
            }
        }else if($this->isJsonHtml($category)){
            $listKey = explode(',',$category['responseListKey']);
            $listHtml = $dataWithList;
            foreach($listKey as $k=>$v){
                $listHtml = $listHtml[$v];
            }
            $this->addHtmlToCrawler($listHtml);
            $crawler = $this->domCrawler;
            if(isset($category['listArea'])){
                $listArea = $category['listArea'];
                $crawler = $crawler->filter("$listArea");
            }
            $item = $category['item'];
            $list = $crawler->filter("$item")
                ->each(function(DOMCrawler $node, $i){
                    return $node->html();
                });
        }else{
            $html = $dataWithList;
            $this->addHtmlToCrawler($html);
            $listArea = $category['listArea'];
            $item = $category['item'];

            $list = $this->domCrawler->filter("$listArea")
                ->filter("$item")
                ->each(function(DOMCrawler $node, $i){
                    return $node->html();
                });

        }

        return $list;
    }

    private function createUrl($uri, $params, $irregParams=null)
    {
        if(is_null($irregParams))
            return $uri . http_build_query($params);
        $url = $uri;
        foreach($params as $k=>$v){
            $url = $url . $k . $v;
        }
        return $url;
    }

    private function addHtmlToCrawler($html)
    {
        $this->domCrawler->clear();
        $this->domCrawler->addHtmlContent($html);
    }

    private function isJson($category)
    {
        return $category['listType'] == 'JSON';
    }

    private function isHtml($category)
    {
        return $category['listType'] == 'HTML';
    }

    private function isJsonHtml($category)
    {
        return $category['listType'] == 'JSONHTML'
            || $category['listType'] == 'HTMLJSON';
    }

    private function doesntExist($item){
        return is_null($item)
            || empty($item)
            || $item == '';
    }

    public function getHtml($url)
    {
        $context = stream_context_create(array('http'=>array('ignore_errors'=>true)));
        $html = @file_get_contents($url, false, $context);
        //$html = iconv("gb2312", "utf-8//IGNORE",$html);
        $html = preg_replace("/[\t\n\r]+/", "", $html);
        $html = preg_replace("/<script.*?<\/script>/", "", $html);
        $html = preg_replace("/<style.*?<\/style>/", "", $html);
        //$html = preg_replace("/style=\".*?\">/", ">", $html);//
        $html = preg_replace("/<html.*?>/", "", $html);
        $html = preg_replace("/<\/html.*?>/", "", $html);
        $html = preg_replace("/<body.*?>/", "", $html);
        $html = preg_replace("/<\/body.*?>/", "", $html);

        return $html;
    }

    public function getCurlHtml($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch,CURLOPT_ENCODING , "deflate");

        $header = [
            'Host: pcflow.dftoutiao.com',
            'Connection: keep-alive',
            'User-Agent: Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36',
            'Accept: */*',
            'Referer: http://mini.eastday.com/',
            'Accept-Encoding: gzip, deflate',
            'Accept-Language: zh-CN,zh;q=0.9'
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public function getJson($url){
        $context = stream_context_create([
            'http'=>[
                'ignore_errors'=>true,
                'method'=>'GET',
                'header'=>'Accept: application/json\r\n'
            ]
        ]);
        $json = @file_get_contents($url, false, $context);
        return $json;
    }
}