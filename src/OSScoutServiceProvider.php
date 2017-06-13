<?php

namespace Jacksunny\OSScout;

use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;
use Jacksunny\OSScout\OpenSearchEngine;
use Config;
use Orzcc\Opensearch\Sdk\CloudsearchClient;

class OSScoutServiceProvider extends ServiceProvider {

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot() {
        //注册阿里云开放搜索为新的scout的引擎 opensearch
        resolve(EngineManager::class)->extend('opensearch', function () {
            $appname = 'yourappname';
            $access_key = Config::get('scout.opensearch.id');
            $secret = Config::get('scout.opensearch.secret');
            $host = Config::get('scout.opensearch.host');
            $key_type = Config::get('scout.opensearch.key_type');
            $opts = array('host' => $host);
            $client = new CloudsearchClient($access_key, $secret, $opts, $key_type);
            return new OpenSearchEngine($appname, $client);
        });
    }

}
