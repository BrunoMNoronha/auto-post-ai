<?php

declare(strict_types=1);

namespace AutoPostAI;

class ApiKeyProvider
{
    public function __construct(
        private OptionsRepository $optionsRepository,
        private Encryption $encryption
    ) {
    }

    public function getApiKey(): string
    {
        $apiKeyEnc = (string) $this->optionsRepository->getOption('map_api_key', '');
        $apiKey = $this->encryption->decrypt($apiKeyEnc);

        if ($apiKey === '' && defined('MAP_OPENAI_API_KEY')) {
            $apiKey = (string) MAP_OPENAI_API_KEY;
        }

        return $apiKey;
    }
}
