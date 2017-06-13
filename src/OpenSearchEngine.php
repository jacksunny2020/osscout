<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Jacksunny\OSScout;

use Laravel\Scout\Builder;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Searchable;
use Orzcc\Opensearch\Sdk\CloudsearchDoc;
use Orzcc\Opensearch\Sdk\CloudsearchSearch;

/**
 * Description of OpenSearchEngine
 * 基于阿里云开放搜索的scout引擎的实现，以便阿里云开放搜索可以使用laravel框架的scout方法实现全文检索
 * @author 施朝阳
 * @date 2017-5-2 18:40:17
 */
class OpenSearchEngine extends Engine {

    //客户端
    private $client;
    //应用名
    private $app_name;

    public function __construct($app_name, $client) {
        $this->client = $client;
        $this->app_name = $app_name;
    }

    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function update($models) {
        /*
         * $indexName = $models->first()->searchableAs();
          $index = $this->algolia->initIndex($indexName);
          $mappedObjects = $models->map(function ($model) {
          $array = $model->toSearchableArray();

          if (empty($array)) {
          return;
          }

          return array_merge(['cmd' => $model->getKey()], $array);
          })->filter()->values()->all();
          $index->addObjects($mappedObjects);
         */
        $tableName = $models->first()->searchableAs();
        $doc_obj = new CloudsearchDoc($this->app_name, $this->client);
        $docs_to_upload = array();
        $mappedModels = $models->map(function ($model) use(&$docs_to_upload) {
                    $array = $model->toSearchableArray();

                    if (empty($array)) {
                        return;
                    }
                    $docs_to_upload[] = array(['cmd' => 'ADD', 'fields' => $array]);
                })->filter()->values()->all();
        //$docs_to_upload[] = $mappedModels;
        //生成json格式字符串
        $json = json_encode($docs_to_upload);
        //var_dump($json);
        // 将文档推送到main表中
        echo $doc_obj->add($json, $tableName);
    }

    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete($models) {
        /*
         * $index = $this->algolia->initIndex($models->first()->searchableAs());
          $index->deleteObjects(
          $models->map(function ($model) {
          return $model->getKey();
          })->values()->all()
          );
         */
        $tableName = $models->first()->searchableAs();
        $doc_obj = new CloudsearchDoc($this->app_name, $this->client);
        $docs_to_upload = array();
        $mappedModels = $models->map(function ($model) use(&$docs_to_upload) {
                    $array = $model->toSearchableArray();

                    if (empty($array)) {
                        return;
                    }
                    $docs_to_upload[] = array(['cmd' => 'DELETE', 'fields' => $array]);
                })->filter()->values()->all();
        //$docs_to_upload[] = $mappedModels;
        //生成json格式字符串
        $json = json_encode($docs_to_upload);
        //var_dump($json);
        // 将文档推送到main表中
        echo $doc_obj->add($json, $tableName);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search(Builder $builder) {
        /*
         * return $this->performSearch($builder, array_filter([
          'numericFilters' => $this->filters($builder),
          'hitsPerPage' => $builder->limit,
          ]));
         * $algolia = $this->algolia->initIndex(
          $builder->index ?: $builder->model->searchableAs()
          );
          if ($builder->callback) {
          return call_user_func(
          $builder->callback,
          $algolia,
          $builder->query,
          $options
          );
          }
          return $algolia->search($builder->query, $options);
         */
        $tableName = $builder->index ?: $builder->model->searchableAs();

        if ($builder->callback) {
            return call_user_func(
                    $builder->callback, $tableName, $builder->query, $options
            );
        }

        $queryString = $builder->query;

        // 实例化一个搜索类
        $search_obj = new CloudsearchSearch($this->client);
        // 指定一个应用用于搜索
        $search_obj->addIndex($this->app_name);
        // 指定搜索关键词
        //$search_obj->setQueryString("default:标题");
        $search_obj->setQueryString($queryString);
        // 指定返回的搜索结果的格式为json
        $search_obj->setFormat("json");
        // 执行搜索，获取搜索结果
        $json = $search_obj->search();
        // 将json类型字符串解码
        $result = json_decode($json, true);
        //print_r($result);
        return $result;
    }

    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return mixed
     */
    public function paginate(Builder $builder, $perPage, $page) {
        return $this->search($builder);
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map($results, $model) {
        /*
         * if (count($results['hits']) === 0) {
          return Collection::make();
          }
          $keys = collect($results['hits'])
          ->pluck('objectID')->values()->all();
          $models = $model->whereIn(
          $model->getQualifiedKeyName(), $keys
          )->get()->keyBy($model->getKeyName());
          return Collection::make($results['hits'])->map(function ($hit) use ($model, $models) {
          $key = $hit['objectID'];

          if (isset($models[$key])) {
          return $models[$key];
          }
          })->filter();
         */
        if (count($results) === 0) {
            return Collection::make();
        }

        return Collection::make($results)->filter();
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount($results): int {
        /*
         * return $results['nbHits'];
         */
        return count($results);
    }

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds($results): \Illuminate\Support\Collection {
        /*
         * return collect($results['hits'])->pluck('objectID')->values();
         */
        return collect($results)->pluck('id')->values();
    }

}
