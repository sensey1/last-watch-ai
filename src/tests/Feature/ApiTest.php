<?php

namespace Tests\Feature;

use App\AiPrediction;
use App\DetectionEvent;
use App\DetectionProfile;
use App\TelegramConfig;
use App\WebRequestConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @test
     */
    public function api_can_get_all_profiles()
    {
        factory(DetectionProfile::class, 10)->create();

        $response = $this->get('/api/profiles');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' =>
                    [0 => [
                        'id',
                        'name',
                        'object_classes',
                        'min_confidence',
                        'use_mask'
                    ]]
            ])
            ->assertJsonCount(10, 'data');
    }

    /**
     * @test
     */
    public function api_can_create_a_profile_without_a_mask()
    {
        $this->json('POST', '/api/profiles', [
            'name' => 'My Awesome Profile',
            'file_pattern' => 'camera123',
            'use_regex' => false,
            'object_classes' => ['car', 'person'],
            'min_confidence' => 0.42
        ])
            ->assertStatus(201)
            ->assertJsonCount(1)
            ->assertJson([
                'data' => [
                    'name' => 'My Awesome Profile',
                    'slug' => 'my-awesome-profile',
                    'file_pattern' => 'camera123',
                    'use_mask' => false,
                    'object_classes' => [
                        0 => 'car',
                        1 =>'person'
                    ],
                    'min_confidence' => 0.42
                ]
            ]);
    }

    /**
     * @test
     */
    public function api_can_get_a_profile()
    {
        $profile = factory(DetectionProfile::class)->create();

        $this->get('/api/profiles/'.$profile->id)
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                'data' => [
                    'name' => $profile->name,
                    'slug' => $profile->slug,
                    'file_pattern' => $profile->file_pattern,
                ]
            ]);
    }

    /**
     * @test
     */
    public function api_can_first_page_of_events()
    {
        factory(DetectionEvent::class, 25)
            ->create()
            ->each(function ($event) {
                $event->aiPredictions()->createMany(
                    factory(AiPrediction::class, 3)->make()->toArray()
                );
            });

        $response = $this->get('/api/events');

        $response
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' =>
                    [0 => [
                        'id',
                        'image_file_name',
                        'detection_profiles_count'
                    ]]
            ])
            ->assertJsonCount(10, 'data');
    }

    /**
     * @test
     */
    public function api_can_get_telegram_configs()
    {
        factory(TelegramConfig::class, 5)->create();

        $this->get('/api/telegram')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' =>
                    [0 => [
                        'id',
                        'name',
                        'token',
                        'chat_id',
                        'created_at'
                    ]]
            ])
            ->assertJsonCount(5, 'data');
    }

    /**
     * @test
     */
    public function api_can_create_a_telegram_config()
    {
        $response = $this->post('/api/telegram', [
            'name' => 'My Bot',
            'token' => 'abc123wra8v7ar9e8wac987wac897ea98ce7w98f7ewa97f',
            'chat_id' => '1192051592'
        ])
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'My Bot',
                    'token' => 'abc123wra8v7ar9e8wac987wac897ea98ce7w98f7ewa97f',
                    'chat_id' => '1192051592'
                ]
            ]);
    }

    /**
     * @test
     */
    public function api_can_get_web_request_configs()
    {
        factory(WebRequestConfig::class, 5)->create();

        $this->get('/api/webRequest')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' =>
                    [0 => [
                        'id',
                        'name',
                        'url'
                    ]]
            ])
            ->assertJsonCount(5, 'data');
    }

    /**
     * @test
     */
    public function api_can_create_a_web_request_config()
    {
        $response = $this->post('/api/webRequest', [
            'name' => 'Web Test',
            'url' => 'http://google.com'
        ])
            ->assertStatus(201)
            ->assertJson([
                'data' => [
                    'name' => 'Web Test',
                    'url' => 'http://google.com'
                ]
            ]);
    }

    /**
     * @test
     */
    public function api_can_attach_a_telegram_subscription() {
        $profile = factory(DetectionProfile::class)->create();

        $config = factory(TelegramConfig::class)->create();

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'telegram',
            'id' => $config->id,
            'value' => true
        ])
            ->assertStatus(200);

        $profile->load(['telegramConfigs']);

        $this->assertCount(1, $profile->telegramConfigs);
        $this->assertEquals($config->name, $profile->telegramConfigs()->first()->name);
    }

    /**
     * @test
     */
    public function api_can_attach_a_telegram_subscription_multiple_times()
    {
        $profile = factory(DetectionProfile::class)->create();

        $config = factory(TelegramConfig::class)->create();

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'telegram',
            'id' => $config->id,
            'value' => true
        ])
            ->assertStatus(200);

        $profile->load(['telegramConfigs']);

        $this->assertCount(1, $profile->telegramConfigs);
        $this->assertEquals($config->name, $profile->telegramConfigs()->first()->name);

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'telegram',
            'id' => $config->id,
            'value' => true
        ]);

        $profile->load(['telegramConfigs']);

        $this->assertCount(1, $profile->telegramConfigs);
        $this->assertEquals($config->name, $profile->telegramConfigs()->first()->name);
    }

    /**
     * @test
     */
    public function api_can_detach_a_telegram_subscription() {
        $profile = factory(DetectionProfile::class)->create();

        $config = factory(TelegramConfig::class)->create();

        $profile->telegramConfigs()->attach($config->id);

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'telegram',
            'id' => $config->id,
            'value' => false
        ])
            ->assertStatus(200);

        $profile->load(['telegramConfigs']);

        $this->assertCount(0, $profile->telegramConfigs);
    }
    /**
     * @test
     */
    public function api_can_attach_a_web_request_subscription() {
        $profile = factory(DetectionProfile::class)->create();

        $config = factory(WebRequestConfig::class)->create();

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'webRequest',
            'id' => $config->id,
            'value' => true
        ])
            ->assertStatus(200);

        $profile->load(['webRequestConfigs']);

        $this->assertCount(1, $profile->webRequestConfigs);
        $this->assertEquals($config->name, $profile->webRequestConfigs()->first()->name);
    }

    /**
     * @test
     */
    public function api_can_attach_a_web_request_subscription_multiple_times()
    {
        $profile = factory(DetectionProfile::class)->create();

        $config = factory(WebRequestConfig::class)->create();

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'webRequest',
            'id' => $config->id,
            'value' => true
        ])
            ->assertStatus(200);

        $profile->load(['webRequestConfigs']);

        $this->assertCount(1, $profile->webRequestConfigs);
        $this->assertEquals($config->name, $profile->webRequestConfigs()->first()->name);

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'webRequest',
            'id' => $config->id,
            'value' => true
        ]);

        $profile->load(['webRequestConfigs']);

        $this->assertCount(1, $profile->webRequestConfigs);
        $this->assertEquals($config->name, $profile->webRequestConfigs()->first()->name);
    }

    /**
     * @test
     */
    public function api_can_detach_a_web_request_subscription() {
        $profile = factory(DetectionProfile::class)->create();

        $config = factory(WebRequestConfig::class)->create();

        $profile->webRequestConfigs()->attach($config->id);

        $response = $this->json('POST', '/api/profiles/'.$profile->id.'/subscriptions', [
            'type' => 'webRequest',
            'id' => $config->id,
            'value' => false
        ])
            ->assertStatus(200);

        $profile->load(['webRequestConfigs']);

        $this->assertCount(0, $profile->webRequestConfigs);
    }
}
