<?php
require __DIR__.'/helper.php';
require __DIR__.'/../src/EventFilter.php';
$events = require __DIR__.'/../data/events.php';
function ids(array $items): array { return array_column($items, 'id'); }
same(ids(filterEvents($events,'')),[1,2,3],'all');
same(ids(filterEvents($events,'php')),[1],'keyword');
same(ids(filterEvents($events,'','tech')),[1,3],'category');
same(ids(filterEvents($events,'','',true)),[1,3],'available');
same(ids(filterEvents($events,'Git','tech',true)),[3],'combined');
same(ids(filterEvents($events,'','design',true)),[],'full-category');
same(ids(filterEvents($events,'','unknown')),[],'unknown-category');
same(count($events),3,'input-unchanged');
finish();
