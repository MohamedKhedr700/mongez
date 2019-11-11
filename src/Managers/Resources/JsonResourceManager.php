<?php

namespace HZ\Illuminate\Mongez\Managers\Resources;

use DateTime;
use Illuminate\Database\Eloquent\Model;
use MongoDB\BSON\UTCDateTime;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

abstract class JsonResourceManager extends JsonResource
{
    /**
     * Request object
     * 
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * The data that will be returned
     * 
     * @var array
     */
    protected $data = [];

    /**
     * Data that must be returned
     * 
     * @const array
     */
    const DATA = [];

    /**
     * Data that should be returned if exists
     * 
     * @const array
     */
    const WHEN_AVAILABLE = [];

    /**
     * Set that columns that will be formatted as dates
     * it could be numeric array or associated array to set the date format for certain columns
     * 
     * @const array
     */
    const DATES = [];

    /**
     * Data that has multiple values based on locale codes
     * 
     * @const array
     */
    const LOCALIZED = [];

    /**
     * List of assets that will have a full url if available
     * 
     * @const array
     */
    const ASSETS = [];

    /**
     * Data that will be returned as a resources
     * 
     * i.ie [city => CityResource::class]
     * 
     * @const array
     */
    const RESOURCES = [];

    /**
     * Data that will be returned as a collection of resources
     * 
     * i.ie [city => CityResource::class]
     * 
     * @const array
     */
    const COLLECTABLE = [];

    /**
     * Assets function for generating full url
     * 
     * @var string
     */
    protected $assetsUrlFunction;

    /**
     * List of keys that will be unset before sending 
     * 
     * @var array
     */
    protected static $disabledKeys = [];

    /**
     * List of keys that will be taken only
     * 
     * @var array
     */
    protected static $allowedKeys = [];

    /**
     * Disable the given list of keys
     * 
     * @param ...$keys
     * @return void
     */
    public static function disable(...$keys) 
    {
        static::$disabledKeys = array_merge(static::$disabledKeys, $keys); 
    }

    /**
     * Disable the given list of keys
     * 
     * @param ...$keys
     * @return void
     */
    public static function only(...$keys) 
    {
        static::$allowedKeys = array_merge(static::$allowedKeys, $keys); 
    }

    /**
     * Transform the resource into an array.
     *
     * @param   \Illuminate\Http\Request  $request
     * @return  array
     */
    public function toArray($request)
    {
        $this->request = $request;

        if (!$this->assetsUrlFunction) {
            $this->assetsUrlFunction = static::assetsFunction();
        }

        $this->collectData(static::DATA);

        $this->collectLocalized(static::LOCALIZED);

        $this->collectAssets(static::ASSETS);

        $this->collectCollectables(static::COLLECTABLE);

        $this->collectResources(static::RESOURCES);

        $this->collectDates(static::DATES);

        $this->filterWhenAvailable(static::WHEN_AVAILABLE);

        $this->extend($request);

        // unset all data from the resource
        if (! empty(static::$disabledKeys)) {
            foreach (static::$disabledKeys as $key) {
                unset($this->data[$key]);
            }    
        }

        if (! empty(static::$allowedKeys)) {
            foreach (array_keys($this->data) as $key) {
                if (! in_array($key, static::$allowedKeys)) {
                    unset($this->data[$key]);
                }
            }
        }

        return $this->data;
    }

    /**
     * Get assets function name
     * 
     * @return string
     */
    public static function assetsFunction(): string
    {
        return config('mongez.resources.assets', 'url');
    }

    /**
     * Get the full url for the given asset path
     * 
     * @param string $path
     * @return string
     */
    public static function assetsUrl(string $path): string
    {
        return call_user_func(static::assetsFunction(), $path);
    }

    /**
     * Collect mandatory data
     *
     * @param array $columns
     * @return JsonResourceManager
     */
    public function collectData(array $columns): JsonResourceManager
    {
        foreach ($columns as $column) {
            $this->data[$column] = $this->$column ?? null;
        }

        return $this;
    }

    /**
     * Collect localized data
     *
     * @param array $columns
     * @return JsonResourceManager
     */
    public function collectLocalized(array $columns): JsonResourceManager
    {
        foreach ($columns as $column) {
            $this->data[$column] = $this->locale($column);
        }

        return $this;
    }

    /**
     * Collect assets
     *
     * @param array $columns
     * @return JsonResourceManager
     */
    public function collectAssets(array $columns): JsonResourceManager
    {
        if ($this->resource instanceof Model) {
            if (method_exists($this->resource, 'info')) {
                $resource = $this->resource->info();
            } else {
                $resource = $this->resource->getAttributes();
            }
        } else {
            $resource = (array) $this->resource;
        }

        foreach ($columns as $column) {
            $asset = Arr::get($resource, $column, null);
            
            if (null === $asset) {
                Arr::set($this->data, $column, null);
                continue;
            }

            $assetUrl =  !is_array($asset) ? call_user_func($this->assetsUrlFunction, $asset) : array_map(function ($asset) {
                return call_user_func($this->assetsUrlFunction, $asset);
            }, $asset);

            Arr::set($this->data, $column, $assetUrl);
        }
        return $this;
    }

    /**
     * Collect Collectable data
     *
     * @param array $columns
     * @return JsonResourceManager
     */
    public function collectCollectables(array $columns): JsonResourceManager
    {
        foreach ($columns as $column => $resource) {
            if (isset($this->$column)) {
                $collection = $this->$column;
                $this->collect($column, $resource, $collection);
            } else {
                $this->data[$column] = [];
            }
        }

        return $this;
    }

    /**
     * Collect resources data
     *
     * @param array $columns
     * @return JsonResourceManager
     */
    public function collectResources(array $columns): JsonResourceManager
    {
        foreach ($columns as $column => $resource) {
            if (isset($this->$column)) {
                $resourceData = $this->$column;
                $this->data[$column] = new $resource((object) $resourceData);
            } else {
                $this->data[$column] = [];
            }
        }

        return $this;
    }

    /**
     * Collect dates
     *
     * @param array $columns
     * @return JsonResourceManage
     */
    public function collectDates(array $columns): JsonResourceManager
    {
        foreach ($columns as $key => $column) {
            $format = 'd-m-Y h:i:s a';

            if (!isset($this->$column)) {
                $this->data[$column] = null;
                continue;
            }

            if (is_string($key)) {
                $format = $column;
                $column = $key;
            }

            if ($this->$column instanceof UTCDateTime) {
                $this->$column = $this->$column->toDateTime();
            } elseif (is_int($this->$column)) {
                $this->$column = new DateTime("@{$this->$column}");
            } elseif (is_array($this->$column) && isset($this->$column['date'])) {
                $this->$column = new DateTime($this->$column['date']);
            }

            $this->data[$column] = [
                'format' => $this->$column->format($format),
                'timestamp' => $this->$column->getTimestamp(),
            ];
        }

        return $this;
    }

    /**
     * Filter when available data
     *
     * @param array $columns
     * @return JsonResourceManager
     */
    public function filterWhenAvailable(array $columns): JsonResourceManager
    {
        foreach ($columns as $column) {
            $value = $this->$column ?? null;
            $dataValue = $this->data[$column] ?? null;

            if (
                !$this->isEmptyValue($value) ||
                ! is_subclass_of($dataValue, self::class) &&
                !$this->isEmptyValue($dataValue)
            ) {
                $this->data[$column] = $dataValue ?? $value;
            } else {
                unset($this->data[$column]);
            }
        }

        return $this;
    }

    /**
     * Check if the given value is empty
     * Empty value is an empty array or a null value.  
     *
     * @param  mixed $value
     * @return boolean
     */
    protected function isEmptyValue($value): bool
    {
        return  is_null($value) || is_array($value) && count($value) == 0;
    }

    /**
     * Collect the given items and set it as collection
     * 
     * @param   string $column
     * @param   string $resource
     * @param   mixed $collection
     * @return  void
     */
    protected function collect($column, $resource, $collection)
    {
        if (is_array($collection)) {
            $collection = collect($collection)->map(function ($item) {
                return (object) $item;
            });
        }

        $resourceDetails = null;

        if (is_array($resource) && isset($resource['resource'])) {
            $resourceDetails = $resource;
            $resource = $resourceDetails['resource'];
        }

        $resources = $resource::collection($collection);

        if ($resourceDetails) {
            $resources->collection = $resources->collection->map(function (JsonResourceManager $resource) use ($resourceDetails) {
                if (!empty($resourceDetails['append'])) {
                    foreach ((array) $resourceDetails['append'] as $type => $columns) {
                        switch ($type) {
                            case 'data':
                                $resource->collectData($columns);
                                break;
                            case 'dates':
                                $resource->collectDates($columns);
                                break;
                            case 'assets':
                                $resource->collectAssets($columns);
                                break;
                            case 'whenAvailable':
                                $resource->filterWhenAvailable($columns);
                                break;
                        }
                    }
                }

                return $resource;
            });
        }

        $this->data[$column] = $resources;
    }

    /**
     * Extend data
     * 
     * @param  \Request $request
     * @return array
     */
    protected function extend($request)
    { }

    /**
     * Get name 
     * 
     * @return mixed
     */
    protected function locale($column)
    {
        if (empty($this->$column)) return null;

        if ($this->request->locale) {
            return $this->$column[$this->request->locale] ?? $this->$column;
        } else {
            return $this->$column;
        }
    }

    /**
     * Get Resource info
     * 
     * @return array
     */
    public function info()
    {
        return $this->toArray(request());
    }
    /**
     * Set more data from outside the resource
     * 
     * @param  string $key
     * @param  mixed $value
     * @return $this
     */
    public function set(string $key, $value)
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Append the given key from the resource to the data array
     * 
     * @param  string $key
     * @return $this
     */
    public function append(string $key)
    {
        return $this->set($key, $this->$key);
    }

    /**
     * Collect resources from array
     * 
     * @param array $collection
     * @return mixed
     */
    public static function collectArray($collection)
    {
        return static::collection(collect($collection)->map(function ($resource) {
            return (object) $resource;
        }));
    }
}
