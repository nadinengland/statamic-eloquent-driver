<?php

namespace Statamic\Eloquent\Events;

use Statamic\Events\Event;

class TypeRetrieved extends Event
{
    /**
     * Fired when a type if requested through a Repository and found, either in
     * the cache or loaded from the database. Currently, only the implemented
     * by the EntryRepository.
     *
     * @param  \Statamic\Contracts\Entry\Entry  $target
     */
    public function __construct(public $target) {}
}
