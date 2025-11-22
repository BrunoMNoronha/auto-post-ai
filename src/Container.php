<?php

declare(strict_types=1);

namespace AutoPostAI;

class Container
{
    private ?OptionsRepository $optionsRepository = null;
    private ?Encryption $encryption = null;
    private ?HttpClient $httpClient = null;
    private ?ApiKeyProvider $apiKeyProvider = null;
    private ?ContentGenerator $contentGenerator = null;
    private ?UsageLogRepository $usageLogRepository = null;
    private ?UsageTracker $usageTracker = null;
    private ?ImageGenerator $imageGenerator = null;
    private ?PostPublisher $postPublisher = null;
    private ?AdminPage $adminPage = null;
    private ?Settings $settings = null;
    private ?AjaxHandlers $ajaxHandlers = null;
    private ?Scheduler $scheduler = null;
    private ?Lifecycle $lifecycle = null;
    private ?JobQueue $jobQueue = null;

    public function getOptionsRepository(): OptionsRepository
    {
        if ($this->optionsRepository === null) {
            $this->optionsRepository = new OptionsRepository();
        }

        return $this->optionsRepository;
    }

    public function getEncryption(): Encryption
    {
        if ($this->encryption === null) {
            $this->encryption = new Encryption();
        }

        return $this->encryption;
    }

    public function getHttpClient(): HttpClient
    {
        if ($this->httpClient === null) {
            $this->httpClient = new WordPressHttpClient();
        }

        return $this->httpClient;
    }

    public function getApiKeyProvider(): ApiKeyProvider
    {
        if ($this->apiKeyProvider === null) {
            $this->apiKeyProvider = new ApiKeyProvider($this->getOptionsRepository(), $this->getEncryption());
        }

        return $this->apiKeyProvider;
    }

    public function getContentGenerator(): ContentGenerator
    {
        if ($this->contentGenerator === null) {
            $this->contentGenerator = new ContentGenerator(
                $this->getHttpClient(),
                $this->getOptionsRepository(),
                $this->getApiKeyProvider(),
                $this->getUsageTracker()
            );
        }

        return $this->contentGenerator;
    }

    public function getUsageTracker(): UsageTracker
    {
        if ($this->usageTracker === null) {
            $this->usageTracker = new UsageTracker($this->getOptionsRepository(), $this->getUsageLogRepository());
        }

        return $this->usageTracker;
    }

    public function getUsageLogRepository(): UsageLogRepository
    {
        if ($this->usageLogRepository === null) {
            global $wpdb;
            $this->usageLogRepository = new UsageLogRepository($wpdb);
        }

        return $this->usageLogRepository;
    }

    public function getImageGenerator(): ImageGenerator
    {
        if ($this->imageGenerator === null) {
            $this->imageGenerator = new ImageGenerator(
                $this->getHttpClient(),
                $this->getApiKeyProvider(),
                $this->getOptionsRepository()
            );
        }

        return $this->imageGenerator;
    }

    public function getPostPublisher(): PostPublisher
    {
        if ($this->postPublisher === null) {
            $this->postPublisher = new PostPublisher();
        }

        return $this->postPublisher;
    }

    public function getAdminPage(): AdminPage
    {
        if ($this->adminPage === null) {
            $this->adminPage = new AdminPage($this->getOptionsRepository(), $this->getUsageTracker());
        }

        return $this->adminPage;
    }

    public function getSettings(): Settings
    {
        if ($this->settings === null) {
            $this->settings = new Settings($this->getOptionsRepository(), $this->getEncryption());
        }

        return $this->settings;
    }

    public function getJobQueue(): JobQueue
    {
        if ($this->jobQueue === null) {
            $this->jobQueue = new JobQueue(
                $this->getContentGenerator(),
                $this->getImageGenerator(),
                $this->getOptionsRepository()
            );
        }

        return $this->jobQueue;
    }

    public function getAjaxHandlers(): AjaxHandlers
    {
        if ($this->ajaxHandlers === null) {
            $this->ajaxHandlers = new AjaxHandlers(
                $this->getContentGenerator(),
                $this->getImageGenerator(),
                $this->getPostPublisher(),
                $this->getOptionsRepository(),
                $this->getApiKeyProvider(),
                $this->getHttpClient(),
                $this->getJobQueue()
            );
        }

        return $this->ajaxHandlers;
    }

    public function getScheduler(): Scheduler
    {
        if ($this->scheduler === null) {
            $this->scheduler = new Scheduler(
                $this->getContentGenerator(),
                $this->getImageGenerator(),
                $this->getPostPublisher(),
                $this->getOptionsRepository()
            );
        }

        return $this->scheduler;
    }

    public function getLifecycle(): Lifecycle
    {
        if ($this->lifecycle === null) {
            $this->lifecycle = new Lifecycle(
                $this->getScheduler(),
                $this->getUsageLogRepository(),
                $this->getUsageTracker()
            );
        }

        return $this->lifecycle;
    }
}