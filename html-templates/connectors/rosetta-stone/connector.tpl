{extends designs/site.tpl}

{block title}Rosseta Stone Connector &mdash; {$dwoo.parent}{/block}

{block content}
    <h1>Rosetta Stone Connector</h1>

    <ul>
        <li><a href="{$connectorBaseUrl}/students.csv" class="button">Download Students Spreadsheet</a></li>
        <li><a href="{$connectorBaseUrl}/launch" class="button">Launch Link</a></li>
    </ul>
{/block}