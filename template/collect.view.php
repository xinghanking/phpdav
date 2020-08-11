<?php
$collectView = <<<COLLECT_VIEW_HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="zh"  xml:lang="zh">
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
    <title>Index of {$showPath}</title>
    <style type="text/css">
        #setpath{
            width: 1000px;
            text-align: right;
        }
        table {
            width: 1000px;
            border-collapse: collapse;
            border-color: darkgrey;
        }
        table thead tr th{
            background-color: lightblue;
            text-align: center;
            height: 40px;
            line-height: 40px;
            vert-align: middle;
        }
        table tbody tr {
            border: 1px;
            height: 40px;
            line-height: 40px;
            vert-align: middle;
        }
        table tr td:first-child{
            padding-left: 10px;
            text-align: left;
        }
        table tr td:nth-child(2){
            width: 120px;
            text-align: right;
        }
        table tr td:last-child{
            width: 200px;
            text-align: center;
        }
        a:link, a:visited {
            border-style: solid;
            border-width: 0;
            border-color: transparent;
        }
        a:hover {
            border-color: gray;
        }
</style>
</head>
<body>
<ul>
<!--上级目录导航
COLLECT_VIEW_HTML;
$upper = dirname($showPath);
if ($upper != $showPath) {
    $collectView .= <<<COLLECT_VIEW_HTML
-->
<li>返回上级目录：<a href="../">{$upper}</a></li>
<!--
COLLECT_VIEW_HTML;
}
$collectView .= <<<COLLECT_VIEW_HTML
-->
    <li>当前资源路径：{$showPath}</li>
</ul>
<hr/>
<table>
    <thead>
        <tr>
            <th>资源名</th>
            <th>大小</th>
            <th>修改日期</th>
        </tr>
    </thead>
    <tbody>
<!--
COLLECT_VIEW_HTML;
foreach ($itemList as $item) {
    $collectView .= <<<COLLECT_VIEW_HTML
资源成员
-->
    <tr>
        <td><a href="{$item['href']}" type="{$item['content_type']}" target="_self">{$item['name']}</a></td>
        <td>{$item['size']}</td>
        <td>{$item['modified']}</td>
    </tr>
<!--
COLLECT_VIEW_HTML;
}
$collectView .= <<<COLLECT_VIEW_HTML
-->
    </tbody>
</table>
</body>
</html>
COLLECT_VIEW_HTML;

return $collectView;
