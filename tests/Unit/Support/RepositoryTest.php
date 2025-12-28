<?php

namespace Samushi\Domion\Tests\Unit\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Samushi\Domion\Support\Repositories;
use Samushi\Domion\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('repo_test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /** @test */
    public function test_it_applies_global_filters_automatically()
    {
        RepoTestModel::create(['name' => 'First']);
        RepoTestModel::create(['name' => 'Second']);

        // Simulojmë request-in për search
        $request = Request::create('/', 'GET', ['search' => 'First']);
        $this->app->instance('request', $request);

        $repository = new class extends Repositories {
            protected function getModel(): Model {
                return new RepoTestModel();
            }
        };

        $results = $repository->all();

        $this->assertCount(1, $results);
        $this->assertEquals('First', $results->first()->name);
    }

    /** @test */
    public function test_it_applies_global_order_filter_automatically()
    {
        RepoTestModel::create(['name' => 'B']);
        RepoTestModel::create(['name' => 'A']);

        // Simulojmë request-in për order__name desc
        $request = Request::create('/', 'GET', ['order__name' => 'desc']);
        $this->app->instance('request', $request);

        $repository = new class extends Repositories {
            protected function getModel(): Model {
                return new RepoTestModel();
            }
        };

        $results = $repository->all();

        $this->assertEquals('B', $results->first()->name);
        $this->assertEquals('A', $results->last()->name);
    }
}

class RepoTestModel extends Model
{
    protected $table = 'repo_test_models';
    protected $fillable = ['name'];
    
    public function getSearchableColumns() {
        return ['name'];
    }

    public function getSortableColumns() {
        return ['name', 'created_at'];
    }
}
