<?php
/**
 * JobConfigurationControllerTest.php
 * Copyright (c) 2018 thegrumpydictator@gmail.com
 *
 * This file is part of Firefly III.
 *
 * Firefly III is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Firefly III is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Firefly III. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Tests\Feature\Controllers\Import;

use FireflyIII\Import\JobConfiguration\FakeJobConfiguration;
use FireflyIII\Models\ImportJob;
use FireflyIII\Repositories\ImportJob\ImportJobRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\MessageBag;
use Log;
use Mockery;
use Tests\TestCase;

/**
 * Class JobConfigurationControllerTest
 */
class JobConfigurationControllerTest extends TestCase
{
    /**
     *
     */
    public function setUp()
    {
        parent::setUp();
        Log::debug(sprintf('Now in %s.', \get_class($this)));
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\JobConfigurationController
     */
    public function testIndex(): void
    {
        $job            = new ImportJob;
        $job->user_id   = $this->user()->id;
        $job->key       = 'Afake_job_' . random_int(1, 1000);
        $job->status    = 'has_prereq';
        $job->provider  = 'fake';
        $job->file_type = '';
        $job->save();

        // mock repositories and configuration handling classes:
        $repository   = $this->mock(ImportJobRepositoryInterface::class);
        $configurator = $this->mock(FakeJobConfiguration::class);

        // mock calls:
        $configurator->shouldReceive('setImportJob')->once();
        $configurator->shouldReceive('configurationComplete')->once()->andReturn(false);
        $configurator->shouldReceive('getNextView')->once()->andReturn('import.fake.apply-rules');
        $configurator->shouldReceive('getNextData')->once()
                     ->andReturn(['rulesOptions' => [1 => 'Y', 0 => 'N',],]);


        $this->be($this->user());
        $response = $this->get(route('import.job.configuration.index', [$job->key]));
        $response->assertStatus(200);
        // expect a redirect to prerequisites
        $response->assertSee('<ol class="breadcrumb">');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\JobConfigurationController
     */
    public function testIndexBadState(): void
    {
        $job            = new ImportJob;
        $job->user_id   = $this->user()->id;
        $job->key       = 'Bfake_job_' . random_int(1, 1000);
        $job->status    = 'some_bad_state';
        $job->provider  = 'fake';
        $job->file_type = '';
        $job->save();

        // mock repositories and configuration handling classes:
        $repository   = $this->mock(ImportJobRepositoryInterface::class);
        $configurator = $this->mock(FakeJobConfiguration::class);

        // mock calls:

        $this->be($this->user());
        $response = $this->get(route('import.job.configuration.index', [$job->key]));
        $response->assertStatus(302);
        $response->assertRedirect(route('import.index'));
        $response->assertSessionHas('error', 'To access this page, your import job cannot have status "some_bad_state".');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\JobConfigurationController
     */
    public function testIndexComplete(): void
    {
        $job            = new ImportJob;
        $job->user_id   = $this->user()->id;
        $job->key       = 'Cfake_job_' . random_int(1, 1000);
        $job->status    = 'has_prereq';
        $job->provider  = 'fake';
        $job->file_type = '';
        $job->save();

        // mock repositories and configuration handling classes:
        $repository   = $this->mock(ImportJobRepositoryInterface::class);
        $configurator = $this->mock(FakeJobConfiguration::class);

        // mock calls:
        $configurator->shouldReceive('setImportJob')->once();
        $configurator->shouldReceive('configurationComplete')->once()->andReturn(true);
        $repository->shouldReceive('updateStatus')->withArgs([Mockery::any(), 'ready_to_run']);

        $this->be($this->user());
        $response = $this->get(route('import.job.configuration.index', [$job->key]));
        $response->assertStatus(302);
        $response->assertRedirect(route('import.job.status.index', [$job->key]));
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\JobConfigurationController
     */
    public function testPost(): void
    {

        $job            = new ImportJob;
        $job->user_id   = $this->user()->id;
        $job->key       = 'Dfake_job_' . random_int(1, 1000);
        $job->status    = 'has_prereq';
        $job->provider  = 'fake';
        $job->file_type = '';
        $job->save();

        $messages = new MessageBag;
        $messages->add('some', 'srrange message');

        // mock repositories and configuration handling classes:
        $repository   = $this->mock(ImportJobRepositoryInterface::class);
        $configurator = $this->mock(FakeJobConfiguration::class);

        // mock calls:
        $configurator->shouldReceive('setImportJob')->once();
        $configurator->shouldReceive('configurationComplete')->once()->andReturn(false);
        $configurator->shouldReceive('configureJob')->withArgs([[]])->once()->andReturn($messages);

        // call thing.
        $this->be($this->user());
        $response = $this->post(route('import.job.configuration.post', [$job->key]));
        $response->assertStatus(302);
        $response->assertRedirect(route('import.job.configuration.index', [$job->key]));
        $response->assertSessionHas('warning', $messages->first());
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\JobConfigurationController
     */
    public function testPostBadState(): void
    {

        $job            = new ImportJob;
        $job->user_id   = $this->user()->id;
        $job->key       = 'Ffake_job_' . random_int(1, 1000);
        $job->status    = 'some_bad_state';
        $job->provider  = 'fake';
        $job->file_type = '';
        $job->save();

        $messages = new MessageBag;
        $messages->add('some', 'srrange message');

        // mock repositories and configuration handling classes:
        $repository   = $this->mock(ImportJobRepositoryInterface::class);
        $configurator = $this->mock(FakeJobConfiguration::class);

        // call thing.
        $this->be($this->user());
        $response = $this->post(route('import.job.configuration.post', [$job->key]));
        $response->assertStatus(302);
        $response->assertRedirect(route('import.index'));
        $response->assertSessionHas('error', 'To access this page, your import job cannot have status "some_bad_state".');
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\JobConfigurationController
     */
    public function testPostComplete(): void
    {

        $job            = new ImportJob;
        $job->user_id   = $this->user()->id;
        $job->key       = 'Efake_job_' . random_int(1, 1000);
        $job->status    = 'has_prereq';
        $job->provider  = 'fake';
        $job->file_type = '';
        $job->save();

        // mock repositories and configuration handling classes:
        $repository   = $this->mock(ImportJobRepositoryInterface::class);
        $configurator = $this->mock(FakeJobConfiguration::class);

        // mock calls:
        $configurator->shouldReceive('setImportJob')->once();
        $configurator->shouldReceive('configurationComplete')->once()->andReturn(true);
        $repository->shouldReceive('updateStatus')->withArgs([Mockery::any(), 'ready_to_run']);

        // call thing.
        $this->be($this->user());
        $response = $this->post(route('import.job.configuration.post', [$job->key]));
        $response->assertStatus(302);
        $response->assertRedirect(route('import.job.status.index', [$job->key]));
    }

    /**
     * @covers \FireflyIII\Http\Controllers\Import\JobConfigurationController
     */
    public function testPostWithUpload(): void
    {
        $file           = UploadedFile::fake()->image('avatar.jpg');
        $job            = new ImportJob;
        $job->user_id   = $this->user()->id;
        $job->key       = 'Dfake_job_' . random_int(1, 1000);
        $job->status    = 'has_prereq';
        $job->provider  = 'fake';
        $job->file_type = '';
        $job->save();

        $messages = new MessageBag;
        $messages->add('some', 'srrange message');

        // mock repositories and configuration handling classes:
        $repository   = $this->mock(ImportJobRepositoryInterface::class);
        $configurator = $this->mock(FakeJobConfiguration::class);

        // mock calls:
        $configurator->shouldReceive('setImportJob')->once();
        $configurator->shouldReceive('configurationComplete')->once()->andReturn(false);
        $configurator->shouldReceive('configureJob')->once()->andReturn($messages);
        $repository->shouldReceive('storeFileUpload')->once()->andReturn(new MessageBag);

        // call thing.
        $this->be($this->user());
        $response = $this->post(route('import.job.configuration.post', [$job->key]), ['import_file' => $file]);
        $response->assertStatus(302);
        $response->assertRedirect(route('import.job.configuration.index', [$job->key]));
        $response->assertSessionHas('warning', $messages->first());
    }


}
