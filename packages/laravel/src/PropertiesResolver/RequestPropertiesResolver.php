<?php

namespace Hybridly\PropertiesResolver;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceResponse;
use Hybridly\LazyProperty;
use Hybridly\Hybridly;
use Hybridly\Support\Arr;

class RequestPropertiesResolver implements PropertiesResolver
{
    public function __construct(
        protected Request $request,
    ) {
    }

    public function resolve(string $component, array $properties, array $persisted): array
    {
        $partial = $this->request->header(Hybridly::PARTIAL_COMPONENT_HEADER) === $component;
        $properties = array_filter($properties, static function ($property) {
            return !($property instanceof LazyProperty);
        });

        // The `only` and `except` headers contain json-encoded array data. We want to use them to
        // retrieve the properties whose paths they describe using dot-notation.
        // We only do that when the request is specifically for partial data though.
        if ($partial && $only = array_filter(json_decode($this->request->header(Hybridly::ONLY_DATA_HEADER, ''), true) ?? [])) {
            $properties = Arr::onlyDot($properties, array_merge($only, $persisted));
        }

        if ($partial && $except = array_filter(json_decode($this->request->header(Hybridly::EXCEPT_DATA_HEADER, ''), true) ?? [])) {
            $properties = Arr::exceptDot($properties, $except);
        }
        
        return $this->resolvePropertyInstances($properties, $this->request);
    }
    
    /**
     * Resolve all necessary class instances in the given props.
     */
    public function resolvePropertyInstances(array $props, Request $request, bool $unpackDotProps = true): array
    {
        foreach ($props as $key => $value) {
            if (\is_object($value) && \is_callable($value)) {
                $value = app()->call($value);
            }

            if ($value instanceof LazyProperty) {
                $value = app()->call($value);
            }

            if ($value instanceof \GuzzleHttp\Promise\PromiseInterface) {
                $value = $value->wait();
            }

            if ($value instanceof ResourceResponse || $value instanceof JsonResource) {
                $value = $value->toResponse($request)->getData(true);
            }

            if ($value instanceof Arrayable) {
                $value = $value->toArray();
            }

            if (\is_array($value)) {
                $value = $this->resolvePropertyInstances($value, $request, false);
            }

            if ($unpackDotProps && str_contains($key, '.')) {
                data_set($props, $key, $value);
                unset($props[$key]);
            } else {
                $props[$key] = $value;
            }
        }

        return $props;
    }
}