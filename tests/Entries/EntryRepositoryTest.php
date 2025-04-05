<?php

namespace Tests\Entries;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Eloquent\Entries\Entry;
use Statamic\Eloquent\Entries\EntryModel;
use Statamic\Eloquent\Entries\EntryRepository;
use Statamic\Eloquent\Events\TypeRetrieved;
use Statamic\Entries\EntryCollection;
use Statamic\Events\CollectionTreeSaved;
use Statamic\Facades\Blink;
use Statamic\Facades\Collection;
use Statamic\Stache\Stache;
use Tests\TestCase;

class EntryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_updates_the_uris_of_all_entries_in_a_collection()
    {
        $collection = Collection::make('blog')->routes('blog/{slug}')->save();

        (new Entry)->id(1)->collection($collection)->slug('alfa')->save();
        (new Entry)->id(2)->collection($collection)->slug('bravo')->save();
        (new Entry)->id(3)->collection($collection)->slug('charlie')->save();

        $collection->routes('posts/{slug}')->save();

        // Assert that the URIs are unchanged, to make sure that saving
        // the collection isn't what caused the URIs to be updated.
        $this->assertEquals([
            '/blog/alfa',
            '/blog/bravo',
            '/blog/charlie',
        ], EntryModel::all()->map->uri->all());

        (new EntryRepository(new Stache))->updateUris($collection);

        $this->assertEquals([
            '/posts/alfa',
            '/posts/bravo',
            '/posts/charlie',
        ], EntryModel::all()->map->uri->all());
    }

    #[Test]
    public function it_updates_the_uris_of_specific_entries_in_a_collection()
    {
        $collection = Collection::make('blog')->routes('blog/{slug}')->save();

        (new Entry)->id(1)->collection($collection)->slug('alfa')->save();
        (new Entry)->id(2)->collection($collection)->slug('bravo')->save();
        (new Entry)->id(3)->collection($collection)->slug('charlie')->save();

        $collection->routes('posts/{slug}')->save();

        // Assert that the URIs are unchanged, to make sure that saving
        // the collection isn't what caused the URIs to be updated.
        $this->assertEquals([
            '/blog/alfa',
            '/blog/bravo',
            '/blog/charlie',
        ], EntryModel::all()->map->uri->all());

        (new EntryRepository(new Stache))->updateUris($collection, [2, 3]);

        $this->assertEquals([
            '/blog/alfa',
            '/posts/bravo',
            '/posts/charlie',
        ], EntryModel::all()->map->uri->all());
    }

    #[Test]
    public function it_updates_the_order_of_all_entries_in_a_collection()
    {
        Event::fake(CollectionTreeSaved::class);

        $collection = Collection::make('blog')
            ->structureContents(['max_depth' => 1])
            ->save();

        (new Entry)->id(1)->collection($collection)->slug('alfa')->save();
        (new Entry)->id(2)->collection($collection)->slug('bravo')->save();
        (new Entry)->id(3)->collection($collection)->slug('charlie')->save();
        (new Entry)->id(4)->collection($collection)->slug('delta')->save();

        $collection->structure()->in('en')->tree([
            ['entry' => 4],
            ['entry' => 2],
            ['entry' => 1],
            ['entry' => 3],
        ])->save();

        // Assert that the order is unchanged, to make sure that saving
        // the structure isn't what caused the order to be updated.
        $this->assertEquals([
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 1,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->order])->all());

        (new EntryRepository(new Stache))->updateOrders($collection);

        $this->assertEquals([
            1 => 3,
            2 => 2,
            3 => 4,
            4 => 1,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->order])->all());
    }

    #[Test]
    public function it_updates_the_order_of_specific_entries_in_a_collection()
    {
        Event::fake(CollectionTreeSaved::class);

        $collection = Collection::make('blog')
            ->structureContents(['max_depth' => 1])
            ->save();

        (new Entry)->id(1)->collection($collection)->slug('alfa')->save();
        (new Entry)->id(2)->collection($collection)->slug('bravo')->save();
        (new Entry)->id(3)->collection($collection)->slug('charlie')->save();
        (new Entry)->id(4)->collection($collection)->slug('delta')->save();

        $collection->structure()->in('en')->tree([
            ['entry' => 4],
            ['entry' => 2],
            ['entry' => 1],
            ['entry' => 3],
        ])->save();

        // Assert that the order is unchanged, to make sure that saving
        // the structure isn't what caused the order to be updated.
        $this->assertEquals([
            1 => 1,
            2 => 1,
            3 => 1,
            4 => 1,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->order])->all());

        (new EntryRepository(new Stache))->updateOrders($collection, [2, 3]);

        $this->assertEquals([
            1 => 1,
            2 => 2,
            3 => 4,
            4 => 1,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->order])->all());
    }

    #[Test]
    public function it_updates_the_parents_of_all_entries_in_a_collection()
    {
        Event::fake(CollectionTreeSaved::class);

        $collection = Collection::make('blog')
            ->structureContents(['root' => true])
            ->save();

        (new Entry)->id(1)->collection($collection)->slug('alfa')->save();
        (new Entry)->id(2)->collection($collection)->slug('bravo')->save();
        (new Entry)->id(3)->collection($collection)->slug('charlie')->save();
        (new Entry)->id(4)->collection($collection)->slug('delta')->save();

        $collection->structure()->in('en')->tree([
            ['entry' => 4],
            ['entry' => 2, 'children' => [
                ['entry' => 1],
            ]],
            ['entry' => 3],
        ])->save();

        // Assert that the parents are unchanged, to make sure that saving
        // the structure isn't what caused the parents to be updated.
        $this->assertEquals([
            1 => null,
            2 => null,
            3 => null,
            4 => null,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->data['parent'] ?? null])->all());

        (new EntryRepository(new Stache))->updateParents($collection);

        $this->assertEquals([
            1 => 2,
            2 => 4,
            3 => 4,
            4 => null,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->data['parent'] ?? null])->all());
    }

    #[Test]
    public function it_updates_the_parents_of_specific_entries_in_a_collection()
    {
        Event::fake(CollectionTreeSaved::class);

        $collection = Collection::make('blog')
            ->structureContents(['root' => true])
            ->save();

        (new Entry)->id(1)->collection($collection)->slug('alfa')->save();
        (new Entry)->id(2)->collection($collection)->slug('bravo')->save();
        (new Entry)->id(3)->collection($collection)->slug('charlie')->save();
        (new Entry)->id(4)->collection($collection)->slug('delta')->save();

        $collection->structure()->in('en')->tree([
            ['entry' => 4],
            ['entry' => 2, 'children' => [
                ['entry' => 1],
            ]],
            ['entry' => 3],
        ])->save();

        // Assert that the parents are unchanged, to make sure that saving
        // the structure isn't what caused the parents to be updated.
        $this->assertEquals([
            1 => null,
            2 => null,
            3 => null,
            4 => null,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->data['parent'] ?? null])->all());

        (new EntryRepository(new Stache))->updateParents($collection, [2, 3]);

        $this->assertEquals([
            1 => null,
            2 => 4,
            3 => 4,
            4 => null,
        ], EntryModel::all()->mapWithKeys(fn ($e) => [$e->id => $e->data['parent'] ?? null])->all());
    }

    #[Test, Group('EntryRepository#findByIds')]
    public function it_gets_entries_by_ids()
    {
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = collect([
            (new Entry)->collection($collection)->slug('foo'),
            (new Entry)->collection($collection)->slug('bar'),
        ])->each->save();

        $actual = (new EntryRepository(new Stache))->findByIds($expected->map->id());

        $this->assertInstanceOf(EntryCollection::class, $actual);
        $this->assertEquals($expected->map->id()->all(), $actual->map->id()->all());
    }

    #[Test, Group('EntryRepository#findByIds')]
    public function it_loads_entries_from_database_given_partial_cache_when_finding_by_ids()
    {
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = collect([
            (new Entry)->collection($collection)->slug('foo'),
            (new Entry)->collection($collection)->slug('bar'),
        ]);

        $expected->first()->save();
        Blink::flush();
        $expected->last()->save();

        $actual = (new EntryRepository(new Stache))->findByIds($expected->map->id());

        $this->assertNotNull($expected->first()->id());
        $this->assertNotSame($expected->first(), $actual->first());
        $this->assertEquals($expected->first()->id(), $actual->first()->id());
        $this->assertNotNull($actual->last());
        $this->assertSame($expected->last(), $actual->last());
    }

    #[Test, Group('EntryRepository#findByIds')]
    public function it_returns_entries_in_exact_order_when_finding_by_ids()
    {
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $entries = collect([
            (new Entry)->collection($collection)->slug('foo'),
            (new Entry)->collection($collection)->slug('bar'),
            (new Entry)->collection($collection)->slug('baz'),
        ])->each->save();

        Blink::flush();

        $expected = collect([2, 0, 1])->map(fn ($index) => $entries[$index]->id())->all();
        $actual = (new EntryRepository(new Stache))->findByIds($expected);

        $this->assertEquals($expected, $actual->map->id()->all());
    }

    #[Test, Group('EntryRepository#findByIds')]
    public function it_skips_missing_entires_when_finding_by_ids()
    {
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = tap((new Entry)->collection($collection)->slug('foo'))->save();

        $actual = (new EntryRepository(new Stache))->findByIds([
            $expected->id(),
            'missing',
        ]);

        $this->assertEquals([$expected->id()], $actual->map->id()->all());
    }

    #[Test, Group('TypeRetrieved')]
    public function it_fires_type_retrieved_event_entry_when_found()
    {
        Event::fake();
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = tap((new Entry)->collection($collection)->slug('foo'))->save();

        (new EntryRepository(new Stache))->find($expected->id());

        Event::assertDispatched(TypeRetrieved::class, function (TypeRetrieved $event) use ($expected) {
            $this->assertSame($expected, $event->target);

            return true;
        });
    }

    #[Test, Group('TypeRetrieved')]
    public function it_fires_type_retrieved_event_when_not_already_in_cache()
    {
        Event::fake();
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = tap((new Entry)->collection($collection)->slug('foo'))->save();

        Blink::flush();
        (new EntryRepository(new Stache))->find($expected->id());

        Event::assertDispatched(TypeRetrieved::class, function (TypeRetrieved $event) use ($expected) {
            $this->assertNotSame($expected, $event->target);
            $this->assertEquals($expected->id(), $event->target->id());

            return true;
        });
    }

    #[Test, Group('TypeRetrieved')]
    public function it_fires_type_retrieved_event_when_entry_found_by_ids()
    {
        Event::fake();
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = collect([
            (new Entry)->collection($collection)->slug('foo'),
            (new Entry)->collection($collection)->slug('bar'),
            (new Entry)->collection($collection)->slug('baz'),
        ])->each->save();

        (new EntryRepository(new Stache))->findByIds($expected->map->id());

        Event::assertDispatchedTimes(TypeRetrieved::class, 3);
    }

    #[Test, Group('TypeRetrieved')]
    public function it_fires_type_retrieved_only_for_found_entries_when_finding_by_ids()
    {
        Event::fake();
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = tap((new Entry)->collection($collection)->slug('foo'))->save();

        (new EntryRepository(new Stache))->findByIds([
            $expected->id(),
            'missing',
        ]);

        Event::assertDispatchedTimes(TypeRetrieved::class, 1);
    }

    #[Test, Group('TypeRetrieved')]
    public function it_loads_from_cache_once_stored()
    {
        $collection = Collection::make('pages')->routes('{slug}')->save();
        $expected = (new Entry)->id(1)->collection($collection)->slug('foo');

        $builder = Mockery::mock(\Statamic\Eloquent\Entries\EntryQueryBuilder::class);
        $builder->shouldNotReceive('where');

        $repo = (new EntryRepository(new Stache));
        $repo->storeInCache($expected);

        $this->assertSame($expected, $repo->find($expected->id()));
    }
}
