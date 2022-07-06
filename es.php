<?php
use \Elasticsearch\ClientBuilder;

require 'vendor/autoload.php';

!defined('ES_HOSTS') && define('ES_HOSTS', '127.0.0.1:9200');

function getEs(): \Elasticsearch\Client
{
    return ClientBuilder::create()->setHosts([ES_HOSTS])->build();
}