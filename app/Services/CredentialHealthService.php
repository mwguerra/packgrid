<?php

namespace App\Services;

use App\Enums\CredentialStatus;
use App\Models\Credential;
use Illuminate\Support\Carbon;
use Throwable;

class CredentialHealthService
{
    public function __construct(private readonly GitProviderClientFactory $clientFactory) {}

    public function test(Credential $credential): Credential
    {
        $credential->last_checked_at = Carbon::now();

        try {
            $this->clientFactory->forCredential($credential)->testConnection();
            $credential->status = CredentialStatus::Ok;
            $credential->last_error = null;
        } catch (Throwable $exception) {
            $credential->status = CredentialStatus::Fail;
            $credential->last_error = $exception->getMessage();
        }

        $credential->save();

        return $credential;
    }
}
