<?php

namespace Samushi\Domion\Tests\Unit\Support\Filters;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Samushi\Domion\Support\Filters\Search;
use Samushi\Domion\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;

class SearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->timestamps();
        });
    }

    /** @test */
    public function test_it_filters_by_search_term()
    {
        $model1 = TestModel::create(['name' => 'John Doe', 'email' => 'john@example.com', 'password' => 'secret']);
        $model2 = TestModel::create(['name' => 'Jane Smith', 'email' => 'jane@example.com', 'password' => 'secret']);

        $request = new Request(['search' => 'John']);
        $filter = new class($request->all()) extends Search {
            protected ?string $name = 'search';
        };
        
        $query = TestModel::query();
        $filter->handle($query, function($q) { return $q; });
        
        $results = $query->get();

        $this->assertCount(1, $results);
        $this->assertEquals('John Doe', $results->first()->name);
    }
}

class TestModel extends Model
{
    protected $table = 'test_models';
    protected $fillable = ['name', 'email', 'password'];
    
    public function getSearchableColumns()
    {
        return ['name', 'email'];
    }
}
