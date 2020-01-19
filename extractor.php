<?php
class Extractor{
    private $url;
    private $blockSize;
    private $saveImage;
    private $rawPage;
    private $ctext;
    private $cblocks;

    private $body;

    //定义过滤正则
    private $reCOMM='#<!--[^\!\[]*?(?<!\/\/)-->#';

    public function __construct($url = "", $blockSize=3)  //
    {
        $this->url=$url;
        $this->blockSize = $blockSize;
        $this->rawPage   = "";
        $this->ctexts    = [];
        $this->cblocks   = [];
    }

    //扔掉无用标签及其内容，主要是script style
    private function strip_html_tagsDOM($dom,$tags){
        foreach($tags as $tag)
        {
            $script_tags = $dom->getElementsByTagName($tag);
            $script_tags_length=$script_tags->length;
            for ($i = 0; $i < $script_tags_length; $i++) {
                $script_tags->item(0)->parentNode->removeChild($script_tags->item(0));
            }
        }
        return $dom;
    }

    public function getRawPage(){
        $resp = file_get_contents($this->url);
        //$resp =iconv("gb2312", "utf-8//IGNORE",$resp);
        //resp.encoding = "UTF-8"
        return $resp;
    }
    public function processTags(){
        $this->body = preg_replace($this->reCOMM, "", $this->body);
        $this->body = strip_tags($this->body);
    }

    //详细处理行块过程
    public function processBlocks(){
        //将每行内容按序扔进数组
        $this->ctexts   = explode("\n",$this->body);
        //去掉每行中无意义的空格
        $this->ctexts=preg_replace('/\s+/','',$this->ctexts);
        $this->textLens=[];
        //计算每行长度
        foreach($this->ctexts as $alineText)
        {
            $this->textLens[]=mb_strlen($alineText);
        }

        $totalLine=count($this->ctexts);

        $this->cblocks=array_fill(0,$totalLine,0);

        //选取当前行和之后的$this->blockSize位长度相加
        for($line=0;$line<$totalLine;$line++)
        {
            $this->cblocks[$line]=array_sum(array_slice($this->textLens,$line,$this->blockSize));
        }

        //找到最大行，作为基准
        $maxTextLen = max($this->cblocks);

        //然后向左向右找到起始和结束位置
        $this->start = $this->end = array_keys($this->cblocks,$maxTextLen)[0];
        while($this->start > 0 and $this->cblocks[$this->start] > min($this->textLens)){
            $this->start -= 1;
        }
        while($this->end < $totalLine - $this->blockSize and $this->cblocks[$this->end] > min($this->textLens)){
            $this->end += 1;
        }

        return "".implode('',array_slice($this->ctexts,$this->start,$this->end-$this->start+1));
    }


    public function getContext(){
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $this->rawPage = '<meta http-equiv="Content-Type" content="text/html;charset=utf-8">'.$this->getRawPage();
        $dom->loadHTML($this->rawPage);
        $this->processTags();

        $dom=$this->strip_html_tagsDOM($dom,["script","style"]);

        $dom->formatOutput = true;
        $this->body =$dom->getElementsByTagName("body")->item(0)->nodeValue;
        return $this->processBlocks();
    }
}


$ext =new Extractor("http://blog.rainy.im/2015/09/02/web-content-and-main-image-extractor/",5);
    print_r($ext->getContext());