<?php

namespace Laravel\Nova\Metrics;

use Laravel\SerializableClosure\SerializableClosure;

trait TransformsResults
{
    /**
     * The callback used to transform the value before display.
     *
     * @var \Laravel\SerializableClosure\UnsignedSerializableClosure|null
     */
    public $transformCallback;

    /**
     * Set the callback used to transform the value before presentation.
     *
     * @param  \Closure(mixed):(mixed)|callable(mixed):(mixed)  $transformCallback
     * @return $this
     */
    public function transform($transformCallback)
    {
        $this->transformCallback = SerializableClosure::unsigned($transformCallback);

        return $this;
    }

    /**
     * Resolve the transformed value result.
     *
     * @param  mixed  $value
     * @return mixed
     */
    public function resolveTransformedValue($value)
    {
        return transform($value, $this->transformCallback ?? static fn ($value) => $value);
    }
}
